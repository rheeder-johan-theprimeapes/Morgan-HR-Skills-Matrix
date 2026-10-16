<?php

declare(strict_types=1);

namespace App\Services;

use App\Capability;
use App\Models\AssessmentRevision;
use App\Models\EvidenceAttachment;
use App\Models\OperationalRecord;
use App\Models\PayBenchmark;
use App\Models\PayRecord;
use App\Models\PdrDecision;
use App\Models\PdrDraft;
use App\Models\PdrReview;
use App\Models\PdrSubmission;
use App\Models\PdrTransition;
use App\Models\SkillTarget;
use App\Models\User;
use App\PdrState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PdrSnapshot
{
    public function __construct(private EmployeeScore $scores, private EvidenceAvailability $evidence, private PdrAccess $access, private EmployeeAccess $people, private ScoreCalculator $calculator) {}

    /**
     * @param  list<string>  $copies
     * @return array<string,mixed>
     */
    public function freeze(User $actor, PdrReview $review, PdrDraft $draft, array &$copies): array
    {
        $person = $review->employee;
        $cycle = $review->cycle;
        $framework = $cycle->framework ?? null;
        if ($person === null || $cycle === null || $framework === null) {
            abort(404);
        }
        $capture = $this->scores->capture($actor, $person, $framework);
        if (! $capture['result']['complete']) {
            throw ValidationException::withMessages(['score' => implode(' ', $capture['result']['blocking_reasons'])]);
        }
        $ids = [];
        foreach ($capture['inputs'] as $input) {
            if ($input['revision_id'] !== null) {
                $ids[] = $input['revision_id'];
            }
        }
        $assessments = [];
        $prefix = 'pdr-snapshots/'.Str::uuid();
        foreach (AssessmentRevision::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get() as $revision) {
            $record = $this->fields($revision, ['id', 'assessment_id', 'revision', 'actor_id', 'assessed_on', 'state', 'skill_level_id', 'comment', 'evidence_notes', 'snapshot']);
            $record['attachments'] = [];
            foreach ($revision->attachments()->orderBy('id')->lockForUpdate()->get() as $file) {
                $attachment = $this->fields($file, ['id', 'original_name', 'mime_type', 'byte_size', 'sha256']);
                $attachment['available'] = false;
                if ($this->evidence->isIntact($file)) {
                    $path = $prefix.'/'.$file->id;
                    $copies[] = $path;
                    if (! Storage::disk('evidence')->copy($file->storage_path, $path)) {
                        throw ValidationException::withMessages(['evidence' => 'The private evidence copy failed. Retry submission.']);
                    }
                    $copy = new EvidenceAttachment(['storage_path' => $path, 'sha256' => $file->sha256, 'byte_size' => $file->byte_size]);
                    if (! $this->evidence->isIntact($copy)) {
                        throw ValidationException::withMessages(['evidence' => 'Evidence changed while copying. Reload and retry.']);
                    }
                    $attachment['available'] = true;
                    $attachment['storage_path'] = $path;
                }
                $record['attachments'][] = $attachment;
            }
            $assessments[] = $record;
        }
        $frozenEvidence = [];
        foreach ($assessments as $assessment) {
            $intact = trim($assessment['evidence_notes'] ?? '') !== '';
            foreach ($assessment['attachments'] as $attachment) {
                $intact = $intact || $attachment['available'];
            }
            $frozenEvidence[$assessment['id']] = $intact;
        }
        foreach ($capture['inputs'] as &$input) {
            $input['evidence_complete'] = $input['revision_id'] !== null && ($frozenEvidence[$input['revision_id']] ?? false);
        }
        unset($input);
        $capture['result'] = $this->calculator->calculate($capture['definition'], $capture['inputs']);
        if (! $capture['result']['complete']) {
            throw ValidationException::withMessages(['evidence' => 'Required evidence was unavailable while freezing this revision. Reload and retry.']);
        }
        $targets = SkillTarget::query()->where('is_active', true)->where(function ($query) use ($person): void {
            $query->where('employee_id', $person->id);
            if ($person->department_id !== null) {
                $query->orWhere('department_id', $person->department_id);
            }
            if ($person->job_role_id !== null) {
                $query->orWhere('job_role_id', $person->job_role_id);
            }
        })->orderBy('id')->lockForUpdate()->get()->map(fn (SkillTarget $target): array => $this->fields($target, ['id', 'skill_id', 'employee_id', 'department_id', 'job_role_id', 'skill_level_id', 'reason']))->all();
        $pay = PayRecord::query()->where('employee_id', $person->id)->whereDoesntHave('correction')->orderBy('effective_on')->orderBy('sequence')->lockForUpdate()->get()->map(fn (PayRecord $row): array => $this->fields($row, ['id', 'sequence', 'effective_on', 'amount_minor', 'currency', 'basis', 'weekly_hours_hundredths', 'weeks_per_year_hundredths', 'source', 'recorded_by', 'supersedes_id', 'correction_reason']))->all();
        $benchmarkId = $draft->pay_proposal['benchmark_id'] ?? null;
        $benchmark = $benchmarkId === null ? null : PayBenchmark::query()->whereKey($benchmarkId)->lockForUpdate()->first();
        $context = OperationalRecord::query()->where('employee_id', $person->id)->whereDoesntHave('correction')->orderBy('sequence')->lockForUpdate()->get()->map(fn (OperationalRecord $row): array => $this->fields($row, ['id', 'sequence', 'metric', 'unit', 'value_hundredths', 'is_interpretation', 'period_start', 'period_end', 'source', 'notes', 'recorded_by', 'supersedes_id', 'correction_reason']))->all();

        return ['schema_version' => 1, 'person' => EmployeeProfile::fromEmployee($person), 'cycle' => ['id' => $cycle->id, 'name' => $cycle->name, 'opens_on' => $cycle->opens_on->toDateString(), 'closes_on' => $cycle->closes_on->toDateString(), 'requires_employee_input' => $cycle->requires_employee_input], 'manager_user_id' => $review->manager_user_id, 'section_template' => $review->section_template, 'employee_section' => $draft->employee_section, 'manager_section' => $draft->manager_section, 'framework' => ['id' => $framework->id, 'name' => $framework->name, 'illustrative' => $framework->illustrative, 'definition' => $capture['definition']], 'score_inputs' => $capture['inputs'], 'score' => $capture['result'], 'assessments' => $assessments, 'targets' => $targets, 'pay_proposal' => $draft->pay_proposal, 'actual_pay' => $pay, 'benchmark' => $benchmark === null ? null : $this->fields($benchmark, ['id', 'job_role_id', 'title', 'company_context', 'duties', 'limitations', 'source_date', 'valid_from', 'valid_to', 'amount_minor', 'currency', 'basis', 'weekly_hours_hundredths', 'weeks_per_year_hundredths', 'source']), 'operational_context' => $context];
    }

    /** @return array<string,mixed> */
    public function project(User $actor, PdrReview $review, PdrSubmission $submission): array
    {
        $this->authorize($actor, $review, $submission);
        $account = $this->people->currentAccount($actor);
        if ($account === null) {
            abort(403);
        }
        $source = $submission->snapshot;
        $page = ['id' => $submission->id, 'review_id' => $review->id, 'submission_number' => $submission->submission_number, 'head_version' => $review->version, 'state' => $review->state->value, 'created_at' => $submission->created_at?->toIso8601String(), 'person' => $source['person'], 'cycle' => $source['cycle'], 'employee_section' => $source['employee_section'], 'manager_section' => $source['manager_section'], 'framework' => $source['framework'], 'score' => $source['score'], 'can_hr_review' => $this->canReview($account, $review) && $review->current_submission_id === $submission->id && $review->state === PdrState::Submitted];
        $files = [];
        foreach ($source['assessments'] as $assessment) {
            foreach ($assessment['attachments'] as $file) {
                $files[] = ['id' => $file['id'], 'name' => $file['original_name'], 'available' => $file['available']];
            }
        }
        if ($account->hasCapability(Capability::ActualPay) && $account->hasCapability(Capability::ApprovePay)) {
            $decision = PdrDecision::query()->where('pdr_submission_id', $submission->id)->first();
            $page['decision'] = $decision === null ? null : ['outcome' => $decision->outcome, 'approved_pay' => $decision->approved_pay, 'reason' => $decision->reason, 'decided_at' => $decision->created_at?->toDateTimeString()];
        }
        $page['can_record_feedback'] = $this->access->canRecordFeedback($actor, $review) && in_array($review->state, [PdrState::Approved, PdrState::FeedbackRecorded, PdrState::Released, PdrState::Acknowledged], true);
        $page['can_decide'] = $this->access->canDecide($actor, $review) && $review->current_submission_id === $submission->id && $review->state === PdrState::HrReviewed;
        $page['evidence'] = $files;
        $page['assessments'] = [];
        foreach ($source['assessments'] as $assessment) {
            $row = array_intersect_key($assessment, array_flip(['id', 'state', 'comment', 'evidence_notes']));
            $row['assessed_on'] = substr($assessment['assessed_on'], 0, 10);
            $row['skill_name'] = 'Frozen assessment';
            $row['rating'] = ucfirst(str_replace('_', ' ', $assessment['state']));
            foreach ($source['framework']['definition']['components'] as $component) {
                foreach ($component['skills'] as $skill) {
                    foreach ($source['score']['components'] as $scoreComponent) {
                        foreach ($scoreComponent['skills'] as $cell) {
                            if ($cell['revision_id'] === $assessment['id'] && $cell['skill_id'] === $skill['skill_id']) {
                                $row['skill_name'] = $skill['name'];
                                foreach ($skill['levels'] as $level) {
                                    if ($level['level_id'] === $assessment['skill_level_id']) {
                                        $row['rating'] = $level['label'];
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $page['assessments'][] = $row;
        }
        $page['transitions'] = $this->access->transitionHistory($actor, $review, $submission->id);
        if ($account->hasCapability(Capability::ActualPay)) {
            $page['actual_pay'] = $source['actual_pay'];
        }
        if ($account->hasCapability(Capability::Benchmarks)) {
            $page['benchmark'] = $source['benchmark'];
        }
        if ($account->hasCapability(Capability::SensitiveContext)) {
            $page['operational_context'] = $source['operational_context'];
        }
        if ($account->hasCapability(Capability::RecommendPay) || $account->hasCapability(Capability::ApprovePay)) {
            $page['pay_proposal'] = $source['pay_proposal'];
            if (! $account->hasCapability(Capability::Benchmarks) && is_array($page['pay_proposal'])) {
                unset($page['pay_proposal']['benchmark_id']);
            }
        }

        return $page;
    }

    public function authorize(User $actor, PdrReview $review, PdrSubmission $submission): void
    {
        abort_unless($submission->pdr_review_id === $review->id && ! $this->access->canEmployee($actor, $review) && ($this->access->canManager($actor, $review) || $this->access->canAdminister($actor, $review) || ($this->access->canDecide($actor, $review) && PdrTransition::query()->where('pdr_submission_id', $submission->id)->where('action', 'hr_reviewed')->exists())), 403);
    }

    public function canReview(User $actor, PdrReview $review): bool
    {
        return $actor->id !== $review->manager_user_id && $this->access->canAdminister($actor, $review);
    }

    /**
     * @param  list<string>  $fields
     * @return array<string,mixed>
     */
    private function fields(Model $model, array $fields): array
    {
        return array_intersect_key($model->getAttributes(), array_flip($fields));
    }
}
