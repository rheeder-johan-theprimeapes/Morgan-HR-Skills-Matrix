<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\PdrDraft;
use App\Models\PdrReview;
use App\Models\PdrSubmission;
use App\Models\PdrTransition;
use App\Models\User;
use App\PdrState;
use App\Services\EmployeeAccess;
use App\Services\PdrSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ReviewPdrSubmission
{
    public function __construct(private EmployeeAccess $people, private PdrSnapshot $snapshots) {}

    /** @param array<string,mixed> $input */
    public function handle(User $actor, PdrReview $review, PdrSubmission $submission, array $input): void
    {
        DB::transaction(function () use ($actor, $review, $submission, $input): void {
            $account = $this->people->currentAccount($actor);
            if ($account === null) {
                abort(403);
            }
            $head = PdrReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->snapshots->canReview($account, $head), 403);
            $values = Validator::make($input, ['expected_version' => ['required', 'integer', 'min:1'], 'action' => ['required', Rule::in(['review', 'return'])], 'reason' => ['required', 'string', 'max:4000']])->validate();
            abort_unless($submission->pdr_review_id === $head->id && $head->current_submission_id === $submission->id && $head->state === PdrState::Submitted && $head->version === (int) $values['expected_version'], 409, 'This revision has already changed. Reload before reviewing.');
            $action = $values['action'] === 'review' ? 'hr_reviewed' : 'hr_returned';
            if ($values['action'] === 'return') {
                $source = PdrDraft::query()->whereKey($submission->pdr_draft_id)->lockForUpdate()->firstOrFail();
                $next = PdrDraft::query()->create(['pdr_review_id' => $head->id, 'draft_number' => $source->draft_number + 1, 'revision' => 1, 'employee_section' => $source->employee_section, 'manager_section' => $source->manager_section, 'pay_proposal' => $source->pay_proposal, 'previous_submission_id' => $submission->id, 'last_saved_by' => $account->id]);
                $head->current_draft_id = $next->id;
                $head->state = PdrState::Draft;
            } else {
                $head->state = PdrState::HrReviewed;
            }
            $head->version++;
            $head->save();
            PdrTransition::query()->create(['pdr_review_id' => $head->id, 'pdr_submission_id' => $submission->id, 'head_version' => $head->version, 'action' => $action, 'reason' => $values['reason'], 'actor_id' => $account->id]);
            AuditEvent::query()->create(['actor_id' => $account->id, 'event' => 'review.'.$action, 'subject_type' => 'pdr_submission', 'subject_id' => $submission->id, 'details' => ['review_id' => $head->id, 'head_version' => $head->version]]);
        }, 3);
    }
}
