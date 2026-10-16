<?php

declare(strict_types=1);

namespace App\Services;

use App\AssessmentKind;
use App\AssessmentState;
use App\Models\Assessment;
use App\Models\Employee;
use App\Models\ScoringFramework;
use App\Models\SkillTarget;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-import-type ScoreResult from ScoreCalculator
 * @phpstan-import-type ScoreInput from ScoreCalculator
 * @phpstan-import-type FrameworkConfig from FrameworkDefinition
 */
class EmployeeScore
{
    public function __construct(private AssessmentAccess $access, private ScoreCalculator $calculator, private EvidenceAvailability $evidence) {}

    /** @return ScoreResult */
    public function preview(User $actor, Employee $employee, ScoringFramework $framework): array
    {
        return $this->capture($actor, $employee, $framework)['result'];
    }

    /** @return array{definition:FrameworkConfig,inputs:list<ScoreInput>,result:ScoreResult} */
    public function capture(User $actor, Employee $employee, ScoringFramework $framework): array
    {
        abort_unless($this->access->canSupervise($actor, $employee), 403);
        $personQuery = Employee::query()->whereKey($employee->id);
        if (DB::transactionLevel() > 0) {
            $personQuery->lockForUpdate();
        }
        $person = $personQuery->firstOrFail();
        $version = ScoringFramework::query()->whereKey($framework->id)->whereNotNull('published_at')->firstOrFail();
        $skillIds = [];
        foreach ($version->definition['components'] as $component) {
            foreach ($component['skills'] as $skill) {
                $skillIds[] = $skill['skill_id'];
            }
        }
        $query = Assessment::query()->where('employee_id', $person->id)->where('kind', AssessmentKind::Manager)->whereIn('skill_id', $skillIds);
        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate()->with(['currentRevision' => fn ($revisions) => $revisions->lockForUpdate()->with(['attachments' => fn ($files) => $files->lockForUpdate()])]);
        } else {
            $query->with(['currentRevision' => fn ($revisions) => $revisions->with('attachments')]);
        }
        $heads = $query->get()->keyBy('skill_id');
        $targetQuery = SkillTarget::query()->where('employee_id', $person->id)->where('is_active', true)->whereIn('skill_id', $skillIds);
        if (DB::transactionLevel() > 0) {
            $targetQuery->lockForUpdate()->with(['level' => fn ($levels) => $levels->lockForUpdate()]);
        } else {
            $targetQuery->with('level');
        }
        $exceptions = $targetQuery->get()->keyBy('skill_id');
        $inputs = [];
        foreach ($version->definition['components'] as $component) {
            foreach ($component['skills'] as $skill) {
                $global = $skill['department_ids'] === [] && $skill['job_role_ids'] === [];
                $exception = $exceptions->get($skill['skill_id']);
                $hasException = $exception !== null;
                $invalidException = $exception !== null && ($exception->level === null || $exception->level->skill_id !== $skill['skill_id'] || ! $exception->level->is_active);
                $applicable = $global || in_array($person->department_id, $skill['department_ids'], true) || in_array($person->job_role_id, $skill['job_role_ids'], true) || $hasException;
                $revision = $heads->get($skill['skill_id'])?->currentRevision;
                $inputs[] = ['skill_id' => $skill['skill_id'], 'applicable' => $applicable, 'blocking_reason' => $invalidException ? $skill['name'].': individual target exception needs HR attention.' : '', 'applicability_source' => $invalidException ? 'Individual target exception needs HR attention' : ($hasException ? 'Individual target exception' : ($applicable ? 'Published applicability' : 'Outside published applicability')), 'state' => $revision->state->value ?? AssessmentState::Unassessed->value, 'level_id' => $revision->skill_level_id ?? null, 'revision_id' => $revision->id ?? null, 'evidence_complete' => $revision !== null && (trim($revision->evidence_notes ?? '') !== '' || $revision->attachments->contains(fn ($attachment): bool => $this->evidence->isIntact($attachment))), 'na_reason_present' => $revision !== null && trim($revision->comment ?? '') !== ''];
            }
        }

        return ['definition' => $version->definition, 'inputs' => $inputs, 'result' => $this->calculator->calculate($version->definition, $inputs)];
    }
}
