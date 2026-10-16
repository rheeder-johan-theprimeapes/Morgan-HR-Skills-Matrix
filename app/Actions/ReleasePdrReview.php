<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\PdrDecision;
use App\Models\PdrFeedback;
use App\Models\PdrRelease;
use App\Models\PdrReview;
use App\Models\PdrSubmission;
use App\Models\PdrTransition;
use App\Models\User;
use App\PdrState;
use App\Services\EmployeeAccess;
use App\Services\EmployeeReviewSummary;
use App\Services\PdrAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReleasePdrReview
{
    public function __construct(private EmployeeAccess $people, private PdrAccess $access, private EmployeeReviewSummary $summaries) {}

    /** @param array<string,mixed> $input */
    public function handle(User $actor, PdrReview $review, array $input): PdrRelease
    {
        return DB::transaction(function () use ($actor, $review, $input): PdrRelease {
            $account = $this->people->currentAccount($actor);
            if ($account === null) {
                abort(403);
            }
            $head = PdrReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->access->canRecordFeedback($account, $head), 403);
            $values = Validator::make($input, ['expected_version' => ['required', 'integer', 'min:1'], 'recorder_reason' => [$this->access->canManager($account, $head) ? 'nullable' : 'required', 'string', 'max:4000']])->validate();
            abort_unless($head->state === PdrState::FeedbackRecorded && $head->version === (int) $values['expected_version'], 409, 'This review is not ready for release or has changed. Reload.');
            $submission = PdrSubmission::query()->whereKey($head->current_submission_id)->where('pdr_review_id', $head->id)->firstOrFail();
            $decision = PdrDecision::query()->where('pdr_submission_id', $submission->id)->where('outcome', 'approve')->firstOrFail();
            $feedback = PdrFeedback::query()->whereKey($head->current_feedback_id)->where('pdr_submission_id', $submission->id)->where('pdr_decision_id', $decision->id)->firstOrFail();
            $release = PdrRelease::query()->create(['pdr_review_id' => $head->id, 'pdr_submission_id' => $submission->id, 'pdr_feedback_id' => $feedback->id, 'summary' => $this->summaries->compose($submission, $feedback, $decision), 'released_by' => $account->id, 'recorder_reason' => $values['recorder_reason'] ?? null]);
            $head->update(['state' => PdrState::Released, 'version' => $head->version + 1, 'current_release_id' => $release->id]);
            PdrTransition::query()->create(['pdr_review_id' => $head->id, 'pdr_submission_id' => $submission->id, 'head_version' => $head->version, 'action' => 'released', 'reason' => 'Employee-safe approved outcome explicitly released.', 'actor_id' => $account->id]);
            AuditEvent::query()->create(['actor_id' => $account->id, 'event' => 'review.released', 'subject_type' => 'pdr_release', 'subject_id' => $release->id, 'details' => ['review_id' => $head->id, 'submission_id' => $submission->id]]);

            return $release;
        }, 3);
    }
}
