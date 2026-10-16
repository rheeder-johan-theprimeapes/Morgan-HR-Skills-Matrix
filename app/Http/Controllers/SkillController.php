<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SaveSkill;
use App\Http\Requests\SaveSkillRequest;
use App\Models\Department;
use App\Models\JobRole;
use App\Models\Skill;
use App\Models\SkillGroup;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SkillController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Skill::class);

        return view('skills.index', ['skills' => Skill::query()->with('group')->withCount('levels')->orderBy('name')->orderBy('id')->paginate(25), 'groups' => SkillGroup::query()->orderBy('display_order')->orderBy('id')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Skill::class);

        return view('skills.form', $this->formData(new Skill));
    }

    public function store(SaveSkillRequest $request, SaveSkill $save): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $skill = $save->handle($actor, null, $request->validated());

        return redirect()->route('skills.show', $skill)->with('status', 'Skill created. Add its rating definitions.');
    }

    public function show(Skill $skill): View
    {
        Gate::authorize('view', $skill);

        return view('skills.show', ['skill' => $skill->load(['group', 'levels', 'departments', 'jobRoles'])]);
    }

    public function edit(Skill $skill): View
    {
        Gate::authorize('update', $skill);

        return view('skills.form', $this->formData($skill->load(['departments', 'jobRoles'])));
    }

    public function update(SaveSkillRequest $request, Skill $skill, SaveSkill $save): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $save->handle($actor, $skill, $request->validated());

        return redirect()->route('skills.show', $skill)->with('status', 'Skill saved.');
    }

    /** @return array<string, mixed> */
    private function formData(Skill $skill): array
    {
        return ['skill' => $skill, 'groups' => SkillGroup::query()->where('is_active', true)->orWhereKey($skill->skill_group_id)->orderBy('display_order')->orderBy('id')->get(), 'departments' => Department::query()->where('is_active', true)->orWhereIn('id', $skill->exists ? $skill->departments->modelKeys() : [])->orderBy('name')->get(), 'jobRoles' => JobRole::query()->where('is_active', true)->orWhereIn('id', $skill->exists ? $skill->jobRoles->modelKeys() : [])->orderBy('name')->get()];
    }
}
