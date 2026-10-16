<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\PdrDraft;
use App\Models\PdrReview;
use App\Models\PdrSubmission;
use App\Models\PdrTransition;
use App\Models\ReviewCycle;
use App\Models\User;
use App\PdrState;
use App\Services\EmployeeAccess;
use App\Services\PdrAccess;
use App\Services\PdrSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class SubmitPdrReview
{
    public function __construct(private EmployeeAccess $people, private PdrAccess $access, private PdrSnapshot $snapshot) {}

    /** @param array<string,mixed> $input */
    public function handle(User $actor, PdrReview $review, array $input): PdrSubmission
    {
        $copies = [];
        $submissionId = null;
        try {
            return DB::transaction(function () use ($actor, $review, $input, &$copies, &$submissionId): PdrSubmission {
                $account = $this->people->currentAccount($actor);
                if ($account === null) {
                    abort(403);
                }
                $cycle = ReviewCycle::query()->whereKey($review->review_cycle_id)->sharedLock()->firstOrFail();
                $head = PdrReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
                abort_unless($this->access->canManager($account, $head), 403);
                $draft = PdrDraft::query()->whereKey($head->current_draft_id)->where('pdr_review_id', $head->id)->lockForUpdate()->firstOrFail();
                abort_unless($head->state === PdrState::Draft && $draft->locked_at === null && $cycle->isOpen(), 409, 'This draft is no longer open for submission. Reload.');
                $values = Validator::make($input, ['expected_version' => ['required', 'integer', 'min:1'], 'expected_revision' => ['required', 'integer', 'min:1']])->validate();
                if ((int) $values['expected_version'] !== $head->version || (int) $values['expected_revision'] !== $draft->revision) {
                    throw ValidationException::withMessages(['expected_revision' => 'The allocation or draft changed. Reload before submitting.']);
                }
                Validator::make(['employee_section' => $draft->employee_section, 'manager_section' => $draft->manager_section], ['employee_section.reflection' => [$cycle->requires_employee_input ? 'required' : 'nullable', 'string'], 'manager_section.summary' => ['required', 'string'], 'manager_section.objectives' => ['required', 'array', 'min:1'], 'manager_section.objectives.*.description' => ['required', 'string'], 'manager_section.objectives.*.measure' => ['required', 'string'], 'manager_section.objectives.*.due_on' => ['required', 'date_format:Y-m-d']])->validate();
                $body = $this->snapshot->freeze($account, $head, $draft, $copies);
                $number = (int) PdrSubmission::query()->where('pdr_review_id', $head->id)->max('submission_number') + 1;
                $submission = PdrSubmission::query()->create(['pdr_review_id' => $head->id, 'pdr_draft_id' => $draft->id, 'submission_number' => $number, 'previous_submission_id' => $draft->previous_submission_id, 'schema_version' => 1, 'snapshot' => $body, 'submitted_by' => $account->id]);
                $submissionId = $submission->id;
                $draft->update(['locked_at' => now()]);
                $head->update(['state' => PdrState::Submitted, 'version' => $head->version + 1, 'current_submission_id' => $submission->id]);
                PdrTransition::query()->create(['pdr_review_id' => $head->id, 'pdr_submission_id' => $submission->id, 'head_version' => $head->version, 'action' => 'submitted', 'reason' => 'Manager submitted the complete draft.', 'actor_id' => $account->id]);
                AuditEvent::query()->create(['actor_id' => $account->id, 'event' => 'review.submitted', 'subject_type' => 'pdr_submission', 'subject_id' => $submission->id, 'details' => ['review_id' => $head->id, 'submission_number' => $number]]);

                return $submission;
            });
        } catch (Throwable $exception) {
            try {
                if ($submissionId === null || ! PdrSubmission::query()->whereKey($submissionId)->exists()) {
                    foreach ($copies as $path) {
                        Storage::disk('evidence')->delete($path);
                    }
                }
            } catch (Throwable $cleanupFailure) {
                report($cleanupFailure);
            }

            throw $exception;
        }
    }
}
