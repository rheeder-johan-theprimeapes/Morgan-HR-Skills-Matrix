<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\ReviewCycle;
use App\Models\ScoringFramework;
use App\Models\User;
use App\Services\EmployeeAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveReviewCycle
{
    public function __construct(private EmployeeAccess $access) {}

    /** @return array<string,array<int,mixed>> */
    public static function rules(?ReviewCycle $cycle = null): array
    {
        return ['code' => $cycle === null ? ['required', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9-]*$/', Rule::unique('review_cycles', 'code')] : ['prohibited'], 'name' => ['required', 'string', 'max:180'], 'scoring_framework_id' => ['required', 'integer'], 'opens_on' => ['required', 'date_format:Y-m-d'], 'closes_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:opens_on'], 'requires_employee_input' => ['required', 'boolean'], 'template_approval_reference' => ['nullable', 'string', 'max:1000'], 'expected_revision' => $cycle === null ? ['nullable', 'integer', 'in:0'] : ['required', 'integer', 'min:1']];
    }

    /** @param array<string,mixed> $input */
    public function handle(User $actor, ?ReviewCycle $cycle, array $input): ReviewCycle
    {
        return DB::transaction(function () use ($actor, $cycle, $input): ReviewCycle {
            Gate::forUser($this->access->currentAccount($actor))->authorize('manage-review-cycles');
            $values = Validator::make($input, self::rules($cycle))->validate();
            $record = $cycle === null ? new ReviewCycle : ReviewCycle::query()->lockForUpdate()->findOrFail($cycle->id);
            if ($record->exists && $record->revision !== (int) $values['expected_revision']) {
                throw ValidationException::withMessages(['expected_revision' => 'This cycle changed. Reload before saving.']);
            }
            $framework = ScoringFramework::query()->whereKey($values['scoring_framework_id'])->whereNotNull('published_at')->lockForUpdate()->first();
            if ($framework === null) {
                throw ValidationException::withMessages(['scoring_framework_id' => 'Choose a published framework version.']);
            }
            if (! $framework->illustrative && trim($values['template_approval_reference'] ?? '') === '') {
                throw ValidationException::withMessages(['template_approval_reference' => 'Record HR approval of the live section templates.']);
            }
            if ($record->exists && $record->scoring_framework_id !== $framework->id && $record->reviews()->lockForUpdate()->first(['id']) !== null) {
                throw ValidationException::withMessages(['scoring_framework_id' => 'An allocated cycle retains its framework. Create another cycle for a different version.']);
            }
            $record->fill(['name' => $values['name'], 'scoring_framework_id' => $framework->id, 'opens_on' => $values['opens_on'], 'closes_on' => $values['closes_on'], 'requires_employee_input' => $values['requires_employee_input'], 'template_approval_reference' => $values['template_approval_reference'] ?? null, 'revision' => ($record->revision ?? 0) + 1]);
            if (! $record->exists) {
                $record->code = $values['code'];
                $record->created_by = $actor->id;
            }
            $record->save();
            AuditEvent::query()->create(['actor_id' => $actor->id, 'event' => 'review.cycle.saved', 'subject_type' => 'review_cycle', 'subject_id' => $record->id, 'details' => ['revision' => $record->revision, 'framework_id' => $framework->id]]);

            return $record;
        }, 3);
    }
}
