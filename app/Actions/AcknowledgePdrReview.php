<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\PdrAcknowledgement;
use App\Models\PdrRelease;
use App\Models\PdrReview;
use App\Models\PdrTransition;
use App\Models\User;
use App\PdrState;
use App\Services\EmployeeAccess;
use App\Services\PdrAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AcknowledgePdrReview
{
    public function __construct(private EmployeeAccess $people, private PdrAccess $access) {}

    /** @param array<string,mixed> $input */
    public function handle(User $actor, PdrReview $review, array $input): PdrAcknowledgement
    {
        return DB::transaction(function () use ($actor, $review, $input): PdrAcknowledgement {
            $account = $this->people->currentAccount($actor);
            if ($account === null) {
                abort(403);
            }
            $head = PdrReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->access->canEmployee($account, $head), 403);
            $values = Validator::make($input, ['expected_version' => ['required', 'integer', 'min:1'], 'choice' => ['required', Rule::in(['receipt', 'disagree'])], 'comment' => ['nullable', 'string', 'max:4000']])->validate();
            abort_unless($head->state === PdrState::Released && $head->version === (int) $values['expected_version'], 409, 'Receipt is already recorded or this release changed. Reload.');
            $release = PdrRelease::query()->whereKey($head->current_release_id)->where('pdr_review_id', $head->id)->where('pdr_submission_id', $head->current_submission_id)->firstOrFail();
            $receipt = PdrAcknowledgement::query()->create(['pdr_release_id' => $release->id, 'employee_id' => $head->employee_id, 'actor_id' => $account->id, 'choice' => $values['choice'], 'comment' => $values['comment'] ?? null]);
            $head->update(['state' => PdrState::Acknowledged, 'version' => $head->version + 1]);
            PdrTransition::query()->create(['pdr_review_id' => $head->id, 'pdr_submission_id' => $head->current_submission_id, 'head_version' => $head->version, 'action' => 'acknowledged', 'reason' => 'Employee recorded receipt; agreement is not inferred.', 'actor_id' => $account->id]);
            AuditEvent::query()->create(['actor_id' => $account->id, 'event' => 'review.receipt.recorded', 'subject_type' => 'pdr_acknowledgement', 'subject_id' => $receipt->id, 'details' => ['review_id' => $head->id, 'choice' => $values['choice']]]);

            return $receipt;
        }, 3);
    }
}
