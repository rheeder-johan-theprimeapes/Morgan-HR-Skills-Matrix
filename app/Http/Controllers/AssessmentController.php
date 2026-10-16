<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SaveAssessment;
use App\AssessmentKind;
use App\Http\Requests\SaveAssessmentRequest;
use App\Models\Assessment;
use App\Models\AssessmentRevision;
use App\Models\Employee;
use App\Models\EvidenceAttachment;
use App\Models\Skill;
use App\Models\User;
use App\Services\AssessmentAccess;
use App\Services\EmployeeProfile;
use App\Services\TargetResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

class AssessmentController extends Controller
{
    public function index(Request $request, Employee $employee, AssessmentAccess $access, TargetResolver $targets): View
    {
        Gate::authorize('view', $employee);
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $isSelf = $actor->employee_id === $employee->id;
        $supervises = $access->canSupervise($actor, $employee);
        if (! $isSelf && ! $supervises) {
            abort(403);
        }
        $kinds = $supervises ? [AssessmentKind::Self->value, AssessmentKind::Manager->value] : [AssessmentKind::Self->value];
        $heads = Assessment::query()->where('employee_id', $employee->id)->whereIn('kind', $kinds)->with('currentRevision')->get();
        $skills = Skill::query()->where('is_active', true)->with(['group', 'departments', 'jobRoles'])->orderBy('skill_group_id')->orderBy('name')->orderBy('id')->get();
        $rows = [];
        $displayedSkillIds = [];
        foreach ($skills as $skill) {
            $resolved = $targets->resolve($employee, $skill);
            if (! $resolved->applicable) {
                continue;
            }
            $displayedSkillIds[] = $skill->id;
            $summaries = [];
            foreach ($kinds as $kind) {
                $head = $heads->first(fn (Assessment $assessment): bool => $assessment->skill_id === $skill->id && $assessment->kind->value === $kind);
                $revision = $head?->currentRevision;
                $summaries[$kind] = ['id' => $head?->id, 'state' => $revision?->state->value, 'ordinal' => $revision?->snapshot['ordinal'] ?? null];
            }
            $rows[] = ['skill_id' => $skill->id, 'name' => $skill->name, 'group' => $skill->group?->name, 'target' => $resolved, 'assessments' => $summaries];
        }

        $archived = [];
        foreach ($heads as $head) {
            $revision = $head->currentRevision;
            if (in_array($head->skill_id, $displayedSkillIds, true) || $revision === null) {
                continue;
            }
            $name = $revision->snapshot['skill_name'] ?? null;
            $archived[] = ['id' => $head->id, 'kind' => $head->kind->value, 'name' => is_string($name) ? $name : 'Historical skill '.$head->skill_id, 'assessed_on' => $revision->assessed_on->toDateString()];
        }

        return view('assessments.index', ['archivedAssessments' => $archived, 'profile' => EmployeeProfile::fromEmployee($employee->load(['department', 'jobRole', 'manager'])), 'rows' => $rows, 'canSelf' => $access->canRecord($actor, $employee, AssessmentKind::Self), 'canManager' => $access->canRecord($actor, $employee, AssessmentKind::Manager)]);
    }

    public function edit(Employee $employee, Skill $skill, string $kind, TargetResolver $targets): View
    {
        $assessmentKind = AssessmentKind::tryFrom($kind);
        if ($assessmentKind === null) {
            abort(404);
        }
        Gate::authorize('create', [Assessment::class, $employee, $assessmentKind]);
        $resolved = $targets->resolve($employee, $skill);
        if (! $resolved->applicable) {
            abort(404);
        }
        $head = Assessment::query()->where('employee_id', $employee->id)->where('skill_id', $skill->id)->where('kind', $kind)->with('currentRevision')->first();

        return view('assessments.form', [
            'profile' => EmployeeProfile::fromEmployee($employee->load(['department', 'jobRole', 'manager'])),
            'skill' => ['id' => $skill->id, 'name' => $skill->name, 'description' => $skill->description, 'allows_na' => $skill->allows_na, 'requires_evidence' => $skill->requires_evidence],
            'levels' => $skill->levels()->where('is_active', true)->get(['id', 'ordinal', 'label', 'guidance'])->map(fn ($level): array => ['id' => $level->id, 'ordinal' => $level->ordinal, 'label' => $level->label, 'guidance' => $level->guidance])->all(),
            'target' => $resolved, 'kind' => $assessmentKind->value, 'expectedRevision' => $head->revision ?? 0,
            'current' => $head?->currentRevision === null ? null : $this->revisionData($head->currentRevision), 'historyId' => $head?->id,
        ]);
    }

    public function store(SaveAssessmentRequest $request, Employee $employee, Skill $skill, SaveAssessment $save): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $file = $request->file('attachment');
        $assessment = $save->handle($actor, $employee, $skill, $request->validated(), $file instanceof UploadedFile ? $file : null);

        return redirect()->route('assessments.history', $assessment)->with('status', 'Assessment saved as revision '.$assessment->revision.'.');
    }

    public function show(Assessment $assessment): View
    {
        Gate::authorize('view', $assessment);
        $person = $assessment->employee;
        if ($person === null) {
            abort(404);
        }

        return view('assessments.history', ['profile' => EmployeeProfile::fromEmployee($person->load(['department', 'jobRole', 'manager'])), 'kind' => $assessment->kind->value, 'skillName' => $assessment->skill?->name, 'revisions' => $assessment->revisions()->with('attachments')->get()->map(fn (AssessmentRevision $revision): array => $this->revisionData($revision))->all()]);
    }

    /** @return array<string, mixed> */
    private function revisionData(AssessmentRevision $revision): array
    {
        return ['revision' => $revision->revision, 'assessed_on' => $revision->assessed_on->toDateString(), 'state' => $revision->state->value,
            'skill_level_id' => $revision->skill_level_id, 'ordinal' => $revision->snapshot['ordinal'] ?? null, 'rating_label' => $revision->snapshot['rating_label'] ?? null,
            'skill_name' => $revision->snapshot['skill_name'] ?? null, 'comment' => $revision->comment, 'evidence_notes' => $revision->evidence_notes,
            'attachments' => $revision->attachments->map(fn (EvidenceAttachment $attachment): array => ['id' => $attachment->id, 'name' => $attachment->original_name, 'byte_size' => $attachment->byte_size])->all()];
    }
}
