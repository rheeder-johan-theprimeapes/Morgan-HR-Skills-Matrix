<?php

declare(strict_types=1);

namespace App\Services;

use App\Capability;
use App\Models\PdrReview;
use App\Models\PdrTransition;
use App\Models\User;
use App\PdrState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PdrAccess
{
    public function __construct(private EmployeeAccess $people, private AssessmentAccess $assessments) {}

    public function canEmployee(User $user, PdrReview $review): bool
    {
        $actor = $this->people->currentAccount($user);

        return $actor !== null && $actor->hasNativeAccess() && $actor->employee_id === $review->employee_id;
    }

    public function canManager(User $user, PdrReview $review): bool
    {
        $actor = $this->people->currentAccount($user);
        $current = $this->currentReview($review);

        return $actor !== null && $actor->hasNativeAccess() && $current !== null && $current->manager_user_id === $actor->id && $current->employee !== null && $this->assessments->canSupervise($actor, $current->employee);
    }

    public function canAdminister(User $user, PdrReview $review): bool
    {
        $actor = $this->people->currentAccount($user);

        return $actor !== null && $actor->hasCapability(Capability::AdministerReviews) && $actor->employee_id !== $review->employee_id && $review->employee !== null && $this->people->canView($actor, $review->employee);
    }

    public function canRecordFeedback(User $user, PdrReview $review): bool
    {
        return ! $this->canEmployee($user, $review) && ($this->canManager($user, $review) || $this->canAdminister($user, $review));
    }

    public function hasDecisionAuthority(User $user): bool
    {
        $actor = $this->people->currentAccount($user);

        return $actor !== null && $actor->hasNativeAccess() && $actor->hasCapability(Capability::DecideReviews) && $actor->hasCapability(Capability::ApprovePay) && $actor->hasCapability(Capability::ActualPay);
    }

    public function canDecide(User $user, PdrReview $review): bool
    {
        $actor = $this->people->currentAccount($user);
        $head = $this->currentReview($review);

        return $actor !== null && $head !== null && $this->hasDecisionAuthority($actor) && $actor->employee_id !== $head->employee_id && $actor->id !== $head->manager_user_id && $head->employee !== null && $this->people->canView($actor, $head->employee);
    }

    public function canInspectDecision(User $user, PdrReview $review): bool
    {
        return $this->canDecide($user, $review) && in_array($review->state, [PdrState::HrReviewed, PdrState::Approved, PdrState::FeedbackRecorded, PdrState::Released, PdrState::Acknowledged], true);
    }

    public function canView(User $user, PdrReview $review): bool
    {
        return $this->canEmployee($user, $review) || $this->canManager($user, $review) || $this->canAdminister($user, $review) || $this->canInspectDecision($user, $review);
    }

    /** @return Builder<PdrReview> */
    public function visible(User $user): Builder
    {
        $actor = $this->people->currentAccount($user);
        $query = PdrReview::query();
        if ($actor === null || ! $actor->hasNativeAccess()) {
            return $query->whereRaw('1 = 0');
        }
        $query->whereIn('employee_id', $this->people->query($actor)->select('id'));
        if ($actor->hasCapability(Capability::AdministerReviews)) {
            return $query;
        }
        $supervised = $this->assessments->supervisedIds($actor);
        $canInspectDecisions = $this->hasDecisionAuthority($actor);

        return $query->where(function (Builder $reviews) use ($actor, $supervised, $canInspectDecisions): void {
            if ($canInspectDecisions) {
                $reviews->orWhereIn('state', [PdrState::HrReviewed, PdrState::Approved, PdrState::FeedbackRecorded, PdrState::Released, PdrState::Acknowledged]);
            }
            $reviews->orWhere('employee_id', $actor->employee_id)->orWhere(function (Builder $assigned) use ($actor, $supervised): void {
                $assigned->where('manager_user_id', $actor->id)->whereIn('employee_id', $supervised);
            });
        });
    }

    /** @return array<string,mixed> */
    public function projectDraft(User $user, PdrReview $review): array
    {
        abort_unless($this->canView($user, $review), 403);
        $record = $this->currentReview($review);
        if ($record === null) {
            abort(404);
        }
        $record->load(['cycle.framework', 'currentDraft', 'employee.department', 'employee.jobRole', 'employee.manager']);
        $person = $record->employee;
        $cycle = $record->cycle;
        $framework = $cycle->framework ?? null;
        if ($person === null || $cycle === null || $framework === null) {
            abort(404);
        }
        $draft = $record->currentDraft;
        if ($draft === null) {
            abort(404);
        }
        $employeeMode = $this->canEmployee($user, $record);
        $managerMode = ! $employeeMode && $this->canManager($user, $record);
        $account = $this->people->currentAccount($user);
        $canPropose = $managerMode && $account !== null && $account->hasCapability(Capability::RecommendPay);
        $page = ['id' => $record->id, 'version' => $record->version, 'state' => $record->state->value, 'profile' => EmployeeProfile::fromEmployee($person), 'cycle' => ['id' => $cycle->id, 'name' => $cycle->name, 'opens_on' => $cycle->opens_on->toDateString(), 'closes_on' => $cycle->closes_on->toDateString(), 'is_open' => $cycle->isOpen(), 'illustrative' => $framework->illustrative, 'requires_employee_input' => $cycle->requires_employee_input, 'framework_id' => $cycle->scoring_framework_id], 'section_profile' => $record->section_profile, 'profile_name' => $record->section_template['name'], 'prompt' => $record->section_template[$employeeMode ? 'employee_prompt' : 'manager_prompt'], 'mode' => $employeeMode ? 'employee' : ($managerMode ? 'manager' : 'inspection'), 'editable' => ($employeeMode || $managerMode) && $record->state === PdrState::Draft && $cycle->isOpen() && $draft->locked_at === null && $person->is_active, 'revision' => $draft->revision, 'employee_section' => $draft->employee_section, 'can_propose' => $canPropose, 'can_record_feedback' => $this->canRecordFeedback($user, $record)];
        if (! $employeeMode) {
            $page['manager_section'] = $draft->manager_section;
            $page['transitions'] = $this->transitionHistory($user, $record);
            if ($account !== null && ($account->hasCapability(Capability::RecommendPay) || $account->hasCapability(Capability::ApprovePay))) {
                $proposal = $draft->pay_proposal;
                if ($proposal !== null && ! $account->hasCapability(Capability::Benchmarks)) {
                    $page['has_restricted_benchmark'] = ($proposal['benchmark_id'] ?? null) !== null;
                    unset($proposal['benchmark_id']);
                }
                $page['pay_proposal'] = $proposal;
            }
        }

        return $page;
    }

    /** @return list<array{action:string,reason:string,created_at:?string,actor:string,submission_number:?int}> */
    public function transitionHistory(User $user, PdrReview $review, ?int $submissionId = null): array
    {
        abort_unless(! $this->canEmployee($user, $review) && ($this->canManager($user, $review) || $this->canAdminister($user, $review) || $this->canDecide($user, $review)), 403);
        $actor = $this->people->currentAccount($user);
        $canReadPayRationale = $actor !== null && $actor->hasCapability(Capability::ActualPay) && $actor->hasCapability(Capability::ApprovePay);
        $query = PdrTransition::query()->where('pdr_review_id', $review->id)->with(['actor:id,name,login_id', 'submission:id,submission_number']);
        if ($submissionId !== null) {
            $query->where('pdr_submission_id', $submissionId);
        }

        return array_values($query->orderByDesc('head_version')->limit(30)->get()->map(fn (PdrTransition $event): array => ['actor' => ($event->actor->name ?? 'Unavailable account').' ('.($event->actor->login_id ?? '#'.$event->actor_id).')', 'submission_number' => $event->submission->submission_number ?? null, 'action' => $event->action, 'reason' => $event->action === 'slt_approved' && ! $canReadPayRationale ? 'A human SLT decision was recorded. Pay reviewers can inspect the restricted rationale.' : $event->reason, 'created_at' => $event->created_at?->toDateTimeString()])->all());
    }

    private function currentReview(PdrReview $review): ?PdrReview
    {
        $query = PdrReview::query()->whereKey($review->id);
        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate()->with(['employee' => fn ($people) => $people->lockForUpdate()]);
        } else {
            $query->with('employee');
        }

        return $query->first();
    }
}
