<?php

declare(strict_types=1);

namespace App\Services;

use App\Capability;
use App\Models\PdrAcknowledgement;
use App\Models\PdrDecision;
use App\Models\PdrFeedback;
use App\Models\PdrRelease;
use App\Models\PdrReview;
use App\Models\PdrSubmission;
use App\Models\User;
use App\PdrState;

class EmployeeReviewSummary
{
    public function __construct(private EmployeeAccess $people, private PdrAccess $access, private DevelopmentAccess $development) {}

    /** @return array<string,mixed> */
    public function compose(PdrSubmission $submission, PdrFeedback $feedback, PdrDecision $decision): array
    {
        $source = $submission->snapshot;
        $goals = [];
        foreach ($source['manager_section']['objectives'] as $goal) {
            $goals[] = array_intersect_key($goal, array_flip(['description', 'measure', 'due_on']));
        }
        $pay = $decision->approved_pay;
        if (! is_array($pay)) {
            abort(409, 'The approved pay outcome is unavailable.');
        }
        $finalPay = ['outcome' => $pay['outcome']];
        if ($pay['outcome'] === 'change') {
            $finalPay += array_intersect_key($pay, array_flip(['amount_minor', 'currency', 'basis', 'effective_on']));
        }

        return ['schema_version' => 1, 'person' => array_intersect_key($source['person'], array_flip(['employee_number', 'name'])), 'cycle' => ['name' => $source['cycle']['name']], 'framework' => ['name' => $source['framework']['name'], 'illustrative' => $source['framework']['illustrative']], 'score' => ['value' => $source['score']['score'], 'band' => $source['score']['band']], 'reflection' => array_intersect_key($source['employee_section'], array_flip(['reflection', 'achievements', 'development_needs'])), 'goals' => $goals, 'feedback' => ['held_on' => $feedback->held_on->toDateString(), 'summary' => $feedback->summary, 'development_message' => $feedback->development_message], 'final_pay' => $finalPay];
    }

    public function canView(User $user, PdrReview $review): bool
    {
        $actor = $this->people->currentAccount($user);

        return $actor !== null && ($this->access->canEmployee($actor, $review) || ($actor->hasCapability(Capability::ActualPay) && $this->access->canView($actor, $review)));
    }

    /** @return array<string,mixed> */
    public function project(User $actor, PdrReview $review): array
    {
        $head = PdrReview::query()->whereKey($review->id)->firstOrFail();
        abort_unless($this->canView($actor, $head) && in_array($head->state, [PdrState::Released, PdrState::Acknowledged], true), 403);
        $release = PdrRelease::query()->whereKey($head->current_release_id)->where('pdr_review_id', $head->id)->where('pdr_submission_id', $head->current_submission_id)->firstOrFail();
        $receipt = PdrAcknowledgement::query()->where('pdr_release_id', $release->id)->first();

        return ['development_employee_id' => $head->employee !== null && $this->development->canView($actor, $head->employee) ? $head->employee_id : null, 'review_id' => $head->id, 'release_id' => $release->id, 'head_version' => $head->version, 'summary' => $release->summary, 'released_at' => $release->created_at?->toDateTimeString(), 'can_acknowledge' => $receipt === null && $this->access->canEmployee($actor, $head), 'receipt' => $receipt === null ? null : ['choice' => $receipt->choice, 'comment' => $receipt->comment, 'recorded_at' => $receipt->created_at?->toDateTimeString()]];
    }
}
