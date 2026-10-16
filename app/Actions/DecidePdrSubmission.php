<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\PdrDecision;
use App\Models\PdrDraft;
use App\Models\PdrReview;
use App\Models\PdrSubmission;
use App\Models\PdrTransition;
use App\Models\User;
use App\PdrState;
use App\Services\EmployeeAccess;
use App\Services\PayTerms;
use App\Services\PdrAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DecidePdrSubmission
{
    public function __construct(private EmployeeAccess $people, private PdrAccess $access) {}

    /** @return array<string,array<int,mixed>> */
    public static function rules(): array
    {
        return ['expected_version' => ['required', 'integer', 'min:1'], 'action' => ['required', Rule::in(['approve', 'return'])], 'reason' => ['required', 'string', 'max:4000'], 'pay_outcome' => ['required_if:action,approve', 'prohibited_unless:action,approve', 'nullable', Rule::in(['change', 'no_change'])], 'amount' => ['required_if:pay_outcome,change', 'prohibited_unless:pay_outcome,change', 'nullable', 'numeric', 'between:0.01,999999999.99', 'regex:/^\d{1,9}(?:\.\d{1,2})?$/'], 'currency' => ['required_if:pay_outcome,change', 'prohibited_unless:pay_outcome,change', 'nullable', Rule::in(PayTerms::CURRENCIES)], 'basis' => ['required_if:pay_outcome,change', 'prohibited_unless:pay_outcome,change', 'nullable', Rule::in(['annual', 'hourly'])], 'effective_on' => ['required_if:pay_outcome,change', 'prohibited_unless:pay_outcome,change', 'nullable', 'date_format:Y-m-d']];
    }

    /** @param array<string,mixed> $input */
    public function handle(User $actor, PdrReview $review, PdrSubmission $submission, array $input): PdrDecision
    {
        return DB::transaction(function () use ($actor, $review, $submission, $input): PdrDecision {
            $account = $this->people->currentAccount($actor);
            if ($account === null) {
                abort(403);
            }
            $head = PdrReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->access->canDecide($account, $head), 403);
            $values = Validator::make($input, self::rules())->validate();
            abort_unless($submission->pdr_review_id === $head->id && $head->current_submission_id === $submission->id && $head->state === PdrState::HrReviewed && $head->version === (int) $values['expected_version'], 409, 'This revision changed or has not completed HR review. Reload.');
            $approvedPay = null;
            if ($values['action'] === 'approve') {
                $approvedPay = ['outcome' => $values['pay_outcome']];
                if ($values['pay_outcome'] === 'change') {
                    $approvedPay += ['amount_minor' => PayTerms::hundredths((string) $values['amount']), 'currency' => $values['currency'], 'basis' => $values['basis'], 'effective_on' => $values['effective_on']];
                }
                $head->state = PdrState::Approved;
            } else {
                $source = PdrDraft::query()->whereKey($submission->pdr_draft_id)->lockForUpdate()->firstOrFail();
                $next = PdrDraft::query()->create(['pdr_review_id' => $head->id, 'draft_number' => $source->draft_number + 1, 'revision' => 1, 'employee_section' => $source->employee_section, 'manager_section' => $source->manager_section, 'pay_proposal' => $source->pay_proposal, 'previous_submission_id' => $submission->id, 'last_saved_by' => $account->id]);
                $head->state = PdrState::Draft;
                $head->current_draft_id = $next->id;
            }
            $decision = PdrDecision::query()->create(['pdr_review_id' => $head->id, 'pdr_submission_id' => $submission->id, 'actor_id' => $account->id, 'outcome' => $values['action'], 'reason' => $values['reason'], 'approved_pay' => $approvedPay]);
            $head->version++;
            $head->save();
            $action = $values['action'] === 'approve' ? 'slt_approved' : 'slt_returned';
            PdrTransition::query()->create(['pdr_review_id' => $head->id, 'pdr_submission_id' => $submission->id, 'head_version' => $head->version, 'action' => $action, 'reason' => $values['reason'], 'actor_id' => $account->id]);
            AuditEvent::query()->create(['actor_id' => $account->id, 'event' => 'review.'.$action, 'subject_type' => 'pdr_decision', 'subject_id' => $decision->id, 'details' => ['review_id' => $head->id, 'submission_id' => $submission->id]]);

            return $decision;
        }, 3);
    }
}
