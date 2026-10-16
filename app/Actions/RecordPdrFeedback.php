<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\PdrDecision;
use App\Models\PdrFeedback;
use App\Models\PdrReview;
use App\Models\PdrTransition;
use App\Models\User;
use App\PdrState;
use App\Services\EmployeeAccess;
use App\Services\PdrAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RecordPdrFeedback
{
    public function __construct(private EmployeeAccess $people, private PdrAccess $access) {}

    /** @return array<string,array<int,mixed>> */
    public static function rules(): array
    {
        return ['expected_version' => ['required', 'integer', 'min:1'], 'held_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'summary' => ['required', 'string', 'max:8000'], 'development_message' => ['nullable', 'string', 'max:8000'], 'recorder_reason' => ['nullable', 'string', 'max:4000']];
    }

    /** @param array<string,mixed> $input */
    public function handle(User $actor, PdrReview $review, array $input): PdrFeedback
    {
        return DB::transaction(function () use ($actor, $review, $input): PdrFeedback {
            $account = $this->people->currentAccount($actor);
            if ($account === null) {
                abort(403);
            }
            $head = PdrReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->access->canRecordFeedback($account, $head), 403);
            $values = Validator::make($input, self::rules())->validate();
            abort_unless(in_array($head->state, [PdrState::Approved, PdrState::FeedbackRecorded], true) && $head->version === (int) $values['expected_version'], 409, 'This review changed or is not awaiting feedback. Reload.');
            $decision = PdrDecision::query()->where('pdr_submission_id', $head->current_submission_id)->where('outcome', 'approve')->firstOrFail();
            Validator::make($values, ['held_on' => ['after_or_equal:'.$decision->created_at?->toDateString()], 'recorder_reason' => [$this->access->canManager($account, $head) ? 'nullable' : 'required', 'string']])->validate();
            $revision = (int) PdrFeedback::query()->where('pdr_submission_id', $head->current_submission_id)->max('revision') + 1;
            $feedback = PdrFeedback::query()->create(['pdr_review_id' => $head->id, 'pdr_submission_id' => $head->current_submission_id, 'pdr_decision_id' => $decision->id, 'revision' => $revision, 'held_on' => $values['held_on'], 'summary' => $values['summary'], 'development_message' => $values['development_message'] ?? null, 'recorded_by' => $account->id, 'recorder_reason' => $values['recorder_reason'] ?? null]);
            $head->update(['state' => PdrState::FeedbackRecorded, 'version' => $head->version + 1, 'current_feedback_id' => $feedback->id]);
            PdrTransition::query()->create(['pdr_review_id' => $head->id, 'pdr_submission_id' => $head->current_submission_id, 'head_version' => $head->version, 'action' => 'feedback_recorded', 'reason' => 'Employee-intended feedback revision '.$revision.' recorded.', 'actor_id' => $account->id]);
            AuditEvent::query()->create(['actor_id' => $account->id, 'event' => 'review.feedback.recorded', 'subject_type' => 'pdr_feedback', 'subject_id' => $feedback->id, 'details' => ['review_id' => $head->id, 'revision' => $revision]]);

            return $feedback;
        }, 3);
    }
}
