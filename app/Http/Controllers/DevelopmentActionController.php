<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SaveDevelopmentAction;
use App\Http\Requests\SaveDevelopmentActionRequest;
use App\Models\AuditEvent;
use App\Models\DevelopmentAction;
use App\Models\DevelopmentRevision;
use App\Models\Employee;
use App\Models\PdrRelease;
use App\Models\PdrReview;
use App\Models\Skill;
use App\Models\SkillTarget;
use App\Models\User;
use App\Services\DevelopmentAccess;
use App\Services\EvidenceAvailability;
use App\Services\TargetResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DevelopmentActionController extends Controller
{
    public function index(Request $request, Employee $employee, DevelopmentAccess $access, TargetResolver $targets): View
    {
        $actor = $this->actor($request);
        abort_unless($access->canView($actor, $employee), 403);
        $actions = DevelopmentAction::query()->where('employee_id', $employee->id)->with('currentRevision.actor')->latest('id')->paginate(25);
        $actions->through(function (DevelopmentAction $action) use ($access): array {
            $revision = $action->currentRevision;
            if ($revision === null) {
                abort(404);
            }

            return ['id' => $action->id] + $access->revisionData($revision);
        });
        $canManage = $employee->is_active && $access->canManage($actor, $employee);
        $skills = [];
        $objectives = [];
        if ($canManage) {
            $targetRows = SkillTarget::query()->where('is_active', true)->where(function ($query) use ($employee): void {
                $query->where('employee_id', $employee->id);
                if ($employee->department_id !== null) {
                    $query->orWhere('department_id', $employee->department_id);
                }
                if ($employee->job_role_id !== null) {
                    $query->orWhere('job_role_id', $employee->job_role_id);
                }
            })->with('level')->get();
            foreach (Skill::query()->where('is_active', true)->with(['group', 'departments', 'jobRoles'])->orderBy('name')->get() as $skill) {
                if ($targets->resolveFrom($employee, $skill, $targetRows)->applicable) {
                    $skills[] = ['id' => $skill->id, 'name' => $skill->name];
                }
            }
            $reviewIds = PdrReview::query()->where('employee_id', $employee->id)->select('id');
            foreach (PdrRelease::query()->whereIn('pdr_review_id', $reviewIds)->latest('id')->limit(50)->get() as $release) {
                foreach ($release->summary['goals'] as $index => $goal) {
                    $objectives[] = ['release_id' => $release->id, 'index' => $index, 'label' => $goal['description']];
                }
            }
        }

        return view('development.index', ['person' => ['id' => $employee->id, 'name' => $employee->name], 'actions' => $actions, 'canManage' => $canManage, 'skills' => $skills, 'objectives' => $objectives, 'selectedSkill' => $request->integer('skill')]);
    }

    public function show(Request $request, DevelopmentAction $developmentAction, DevelopmentAccess $access): View
    {
        $page = $access->project($this->actor($request), $developmentAction);
        $history = $developmentAction->revisions()->with('actor')->paginate(20);
        $history->through(fn (DevelopmentRevision $row): array => $access->revisionData($row));

        return view('development.show', ['page' => $page, 'history' => $history]);
    }

    public function store(SaveDevelopmentActionRequest $request, Employee $employee, SaveDevelopmentAction $save): RedirectResponse
    {
        $file = $request->file('attachment');
        $action = $save->handle($this->actor($request), $employee, null, $request->validated(), $file instanceof UploadedFile ? $file : null);

        return redirect()->route('development.show', $action)->with('status', 'Development plan created.');
    }

    public function update(SaveDevelopmentActionRequest $request, DevelopmentAction $developmentAction, SaveDevelopmentAction $save): RedirectResponse
    {
        $person = $developmentAction->employee;
        if ($person === null) {
            abort(404);
        }$file = $request->file('attachment');
        $action = $save->handle($this->actor($request), $person, $developmentAction, $request->validated(), $file instanceof UploadedFile ? $file : null);

        return redirect()->route('development.show', $action)->with('status', 'Development revision saved.');
    }

    public function evidence(Request $request, DevelopmentRevision $developmentRevision, DevelopmentAccess $access, EvidenceAvailability $evidence): StreamedResponse
    {
        $actor = $this->actor($request);
        $person = $developmentRevision->action?->employee;
        if ($person === null) {
            abort(404);
        }abort_unless($access->canView($actor, $person), 403);
        $path = $developmentRevision->storage_path;
        $size = $developmentRevision->byte_size;
        $hash = $developmentRevision->sha256;
        if (! is_string($path) || ! is_int($size) || ! is_string($hash) || ! Storage::disk('evidence')->exists($path)) {
            abort(404, 'Completion evidence is unavailable. Contact HR.');
        }
        abort_unless($evidence->isIntactFile($path, $size, $hash), 409, 'Completion evidence integrity could not be confirmed. Contact HR.');
        AuditEvent::query()->create(['actor_id' => $actor->id, 'event' => 'development.evidence.downloaded', 'subject_type' => 'development_revision', 'subject_id' => $developmentRevision->id, 'details' => ['action_id' => $developmentRevision->development_action_id]]);

        return Storage::disk('evidence')->download($path, $developmentRevision->original_name, ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        return $actor;
    }
}
