<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\Employee;
use App\Models\PdrDraft;
use App\Models\PdrReview;
use App\Models\ReviewCycle;
use App\Models\User;
use App\PdrState;
use App\Services\AssessmentAccess;
use App\Services\EmployeeAccess;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AllocatePdrReview
{
    public function __construct(private EmployeeAccess $access, private AssessmentAccess $assessments) {}

    /** @return array<string,array<int,mixed>> */
    public static function rules(): array
    {
        return ['employee_id' => ['required', 'integer'], 'manager_user_id' => ['required', 'integer'], 'section_profile' => ['required', Rule::in(['production', 'office', 'leadership'])], 'expected_version' => ['required', 'integer', 'min:0'], 'reason' => ['nullable', 'string', 'max:1000']];
    }

    /** @param array<string,mixed> $input */
    public function handle(User $actor, ReviewCycle $cycle, array $input, ?PdrReview $review = null): PdrReview
    {
        try {
            return DB::transaction(function () use ($actor, $cycle, $input, $review): PdrReview {
                $account = $this->access->currentAccount($actor);
                Gate::forUser($account)->authorize('manage-review-cycles');
                $values = Validator::make($input, self::rules())->validate();
                $cycleRecord = ReviewCycle::query()->lockForUpdate()->findOrFail($cycle->id);
                $person = Employee::query()->whereKey($values['employee_id'])->lockForUpdate()->firstOrFail();
                if ($account === null || ! $this->access->canView($account, $person)) {
                    abort(403);
                }
                $manager = User::query()->whereKey($values['manager_user_id'])->lockForUpdate()->first();
                if ($manager === null || ! $person->is_active || ! $this->assessments->canSupervise($manager, $person)) {
                    throw ValidationException::withMessages(['manager_user_id' => 'Choose an active account that currently supervises this employee.']);
                }
                $record = $review === null ? new PdrReview : PdrReview::query()->lockForUpdate()->findOrFail($review->id);
                if ($record->exists) {
                    if ($record->review_cycle_id !== $cycleRecord->id || $record->employee_id !== $person->id) {
                        abort(404);
                    }
                    abort_unless($record->state === PdrState::Draft, 409, 'A submitted allocation cannot be rewritten.');
                    if ($record->version !== (int) $values['expected_version']) {
                        throw ValidationException::withMessages(['expected_version' => 'Allocation changed. Reload before reassigning.']);
                    }
                    if (trim($values['reason'] ?? '') === '') {
                        throw ValidationException::withMessages(['reason' => 'Explain the reassignment.']);
                    }
                } elseif ((int) $values['expected_version'] !== 0 || PdrReview::query()->where('review_cycle_id', $cycleRecord->id)->where('employee_id', $person->id)->lockForUpdate()->first(['id']) !== null) {
                    throw ValidationException::withMessages(['employee_id' => 'This person already has an allocation in the cycle.']);
                }
                $template = config('pdr.profiles.'.$values['section_profile']);
                if (! is_array($template)) {
                    throw ValidationException::withMessages(['section_profile' => 'Choose a configured profile.']);
                }
                $record->fill(['review_cycle_id' => $cycleRecord->id, 'employee_id' => $person->id, 'manager_user_id' => $manager->id, 'section_profile' => $values['section_profile'], 'section_template' => $template, 'version' => ($record->version ?? 0) + 1, 'allocated_by' => $actor->id]);
                if (! $record->exists) {
                    $record->state = PdrState::Draft;
                }
                $record->save();
                if ($record->current_draft_id === null) {
                    $draft = PdrDraft::query()->create(['pdr_review_id' => $record->id, 'draft_number' => 1, 'revision' => 1, 'employee_section' => [], 'manager_section' => []]);
                    $record->current_draft_id = $draft->id;
                    $record->save();
                }
                AuditEvent::query()->create(['actor_id' => $actor->id, 'event' => 'review.allocated', 'subject_type' => 'pdr_review', 'subject_id' => $record->id, 'details' => ['cycle_id' => $cycleRecord->id, 'employee_id' => $person->id, 'manager_user_id' => $manager->id, 'version' => $record->version, 'reason' => $values['reason'] ?? null]]);

                return $record;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['employee_id' => 'This person already has an allocation in the cycle.']);
        }
    }
}
