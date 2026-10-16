<?php

declare(strict_types=1);

namespace App\Actions;

use App\Capability;
use App\Models\AuditEvent;
use App\Models\PayBenchmark;
use App\Models\PdrDraft;
use App\Models\PdrReview;
use App\Models\ReviewCycle;
use App\Models\User;
use App\PdrState;
use App\Services\EmployeeAccess;
use App\Services\PayTerms;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SavePdrDraft
{
    public function __construct(private EmployeeAccess $access) {}

    /** @return array<string,array<int,mixed>> */
    public static function rules(string $section, bool $canPropose, bool $canBenchmark): array
    {
        $rules = ['section' => ['required', Rule::in(['employee', 'manager'])], 'expected_revision' => ['required', 'integer', 'min:1'], 'employee_id' => ['prohibited'], 'manager_user_id' => ['prohibited'], 'last_saved_by' => ['prohibited']];
        if ($section === 'employee') {
            return $rules + ['employee_section' => ['required', 'array:reflection,achievements,development_needs'], 'employee_section.reflection' => ['nullable', 'string', 'max:8000'], 'employee_section.achievements' => ['nullable', 'string', 'max:8000'], 'employee_section.development_needs' => ['nullable', 'string', 'max:8000'], 'manager_section' => ['prohibited'], 'pay_proposal' => ['prohibited']];
        }
        $rules += ['employee_section' => ['prohibited'], 'manager_section' => ['required', 'array:summary,role_notes,objectives'], 'manager_section.summary' => ['nullable', 'string', 'max:8000'], 'manager_section.role_notes' => ['nullable', 'string', 'max:8000'], 'manager_section.objectives' => ['nullable', 'array', 'max:20'], 'manager_section.objectives.*' => ['array:description,measure,due_on'], 'manager_section.objectives.*.description' => ['nullable', 'string', 'max:1000'], 'manager_section.objectives.*.measure' => ['nullable', 'string', 'max:1000'], 'manager_section.objectives.*.due_on' => ['nullable', 'date_format:Y-m-d']];
        if (! $canPropose) {
            return $rules + ['pay_proposal' => ['prohibited']];
        }

        return $rules + ['pay_proposal' => ['nullable', 'array:outcome,amount,currency,basis,effective_on,reason,benchmark_id'], 'pay_proposal.outcome' => ['required_with:pay_proposal.reason,pay_proposal.benchmark_id', 'nullable', Rule::in(['propose', 'no_change'])], 'pay_proposal.amount' => ['required_if:pay_proposal.outcome,propose', 'prohibited_unless:pay_proposal.outcome,propose', 'nullable', 'numeric', 'between:0.01,999999999.99', 'regex:/^\d{1,9}(?:\.\d{1,2})?$/'], 'pay_proposal.currency' => ['required_if:pay_proposal.outcome,propose', 'prohibited_unless:pay_proposal.outcome,propose', 'nullable', Rule::in(PayTerms::CURRENCIES)], 'pay_proposal.basis' => ['required_if:pay_proposal.outcome,propose', 'prohibited_unless:pay_proposal.outcome,propose', 'nullable', Rule::in(['annual', 'hourly'])], 'pay_proposal.effective_on' => ['required_if:pay_proposal.outcome,propose', 'prohibited_unless:pay_proposal.outcome,propose', 'nullable', 'date_format:Y-m-d'], 'pay_proposal.reason' => ['required_if:pay_proposal.outcome,propose,no_change', 'nullable', 'string', 'max:4000'], 'pay_proposal.benchmark_id' => $canBenchmark ? ['nullable', 'integer', 'min:1'] : ['prohibited']];
    }

    /** @param array<string,mixed> $input */
    public function handle(User $actor, PdrReview $review, array $input): PdrDraft
    {
        return DB::transaction(function () use ($actor, $review, $input): PdrDraft {
            $account = $this->access->currentAccount($actor);
            if ($account === null) {
                abort(403);
            }
            $cycle = ReviewCycle::query()->whereKey($review->review_cycle_id)->sharedLock()->firstOrFail();
            $record = PdrReview::query()->lockForUpdate()->findOrFail($review->id);
            $section = is_string($input['section'] ?? null) ? $input['section'] : '';
            if (! in_array($section, ['employee', 'manager'], true)) {
                throw ValidationException::withMessages(['section' => 'Choose the owned section.']);
            }
            Gate::forUser($account)->authorize($section === 'employee' ? 'editEmployee' : 'editManager', $record);
            $person = $record->employee;
            if ($person === null) {
                abort(404);
            }
            $draft = PdrDraft::query()->whereKey($record->current_draft_id)->where('pdr_review_id', $record->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->state === PdrState::Draft && $draft->locked_at === null && $cycle->isOpen() && $person->is_active, 409, 'This draft is locked or outside the cycle window. Reload the review.');
            $values = Validator::make($input, self::rules($section, $account->hasCapability(Capability::RecommendPay), $account->hasCapability(Capability::Benchmarks)))->validate();
            if ($draft->revision !== (int) $values['expected_revision']) {
                throw ValidationException::withMessages(['expected_revision' => 'This draft changed. Your input is retained; reload before saving again.']);
            }
            if ($section === 'employee') {
                $draft->employee_section = $values['employee_section'];
            } else {
                $managerSection = $values['manager_section'];
                if (isset($managerSection['objectives'])) {
                    $managerSection['objectives'] = array_values(array_filter($managerSection['objectives'], fn (array $goal): bool => trim($goal['description'] ?? '') !== '' || trim($goal['measure'] ?? '') !== '' || ($goal['due_on'] ?? null) !== null));
                }
                $draft->manager_section = $managerSection;
                if (array_key_exists('pay_proposal', $values)) {
                    $proposal = $values['pay_proposal'];
                    $canManageBenchmarks = $account->hasCapability(Capability::Benchmarks);
                    $priorBenchmarkId = $draft->pay_proposal['benchmark_id'] ?? null;
                    if ($priorBenchmarkId !== null && ! is_int($priorBenchmarkId)) {
                        throw ValidationException::withMessages(['pay_proposal.benchmark_id' => 'The retained source reference needs HR attention.']);
                    }
                    if (! is_array($proposal) || ($proposal['outcome'] ?? null) === null) {
                        if (! $canManageBenchmarks && $priorBenchmarkId !== null) {
                            throw ValidationException::withMessages(['pay_proposal.outcome' => 'The existing source citation is retained. Choose an outcome or ask HR to change the restricted source.']);
                        }
                        $draft->pay_proposal = null;
                    } else {
                        $benchmarkId = $canManageBenchmarks ? (isset($proposal['benchmark_id']) ? (int) $proposal['benchmark_id'] : null) : $priorBenchmarkId;
                        if ($canManageBenchmarks && $benchmarkId !== null) {
                            $benchmark = PayBenchmark::query()->whereKey($benchmarkId)->lockForUpdate()->first();
                            if ($benchmark === null || $benchmark->job_role_id !== $person->job_role_id || PayBenchmark::query()->where('supersedes_id', $benchmarkId)->lockForUpdate()->first(['id']) !== null) {
                                throw ValidationException::withMessages(['pay_proposal.benchmark_id' => 'Choose current benchmark evidence for this job role.']);
                            }
                        }
                        $draft->pay_proposal = ['outcome' => $proposal['outcome'], 'amount_minor' => isset($proposal['amount']) ? PayTerms::hundredths((string) $proposal['amount']) : null, 'currency' => $proposal['currency'] ?? null, 'basis' => $proposal['basis'] ?? null, 'effective_on' => $proposal['effective_on'] ?? null, 'reason' => $proposal['reason'], 'benchmark_id' => $benchmarkId];
                    }
                }
            }
            $draft->revision++;
            $draft->last_saved_by = $actor->id;
            $draft->save();
            AuditEvent::query()->create(['actor_id' => $actor->id, 'event' => 'review.draft.saved', 'subject_type' => 'pdr_draft', 'subject_id' => $draft->id, 'details' => ['review_id' => $record->id, 'revision' => $draft->revision, 'section' => $section]]);

            return $draft;
        }, 3);
    }
}
