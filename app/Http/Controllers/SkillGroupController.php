<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SaveSkillGroup;
use App\Http\Requests\SaveSkillGroupRequest;
use App\Models\SkillGroup;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SkillGroupController extends Controller
{
    public function store(SaveSkillGroupRequest $request, SaveSkillGroup $save): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $save->handle($actor, null, $request->validated());

        return redirect()->route('skills.index')->with('status', 'Group created.');
    }

    public function edit(SkillGroup $skillGroup): View
    {
        Gate::authorize('manage-skills');

        return view('skills.group', ['group' => $skillGroup]);
    }

    public function update(SaveSkillGroupRequest $request, SkillGroup $skillGroup, SaveSkillGroup $save): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $save->handle($actor, $skillGroup, $request->validated());

        return redirect()->route('skills.index')->with('status', 'Group saved.');
    }
}
