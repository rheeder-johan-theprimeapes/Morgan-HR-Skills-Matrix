<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SaveSkillTarget;
use App\Http\Requests\SaveSkillTargetRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobRole;
use App\Models\Skill;
use App\Models\SkillTarget;
use App\Models\User;
use App\Services\EmployeeAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SkillTargetController extends Controller
{
    public function index(Request $request, Skill $skill, EmployeeAccess $access): View
    {
        Gate::authorize('manage-skills');
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $people = $access->query($actor)->orderBy('name')->orderBy('id')->get();
        $targets = SkillTarget::query()->where('skill_id', $skill->id)->where(fn ($query) => $query->whereNull('employee_id')->orWhereIn('employee_id', $people->modelKeys()))->with(['level', 'employee', 'department', 'jobRole'])->orderBy('id')->paginate(25);

        return view('skills.targets', ['skill' => $skill->load(['levels', 'group']), 'targets' => $targets, 'subjects' => ['department' => Department::query()->where('is_active', true)->orderBy('name')->get(), 'job_role' => JobRole::query()->where('is_active', true)->orderBy('name')->get(), 'employee' => $people->filter(fn (Employee $person): bool => $person->is_active)]]);
    }

    public function store(SaveSkillTargetRequest $request, Skill $skill, SaveSkillTarget $save): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $save->handle($actor, $skill, $request->validated());

        return redirect()->route('skills.targets.index', $skill)->with('status', 'Target saved with HR approval.');
    }
}
