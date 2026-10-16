<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AddSkillLevel;
use App\Http\Requests\AddSkillLevelRequest;
use App\Models\Skill;
use App\Models\SkillLevel;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SkillLevelController extends Controller
{
    public function store(AddSkillLevelRequest $request, Skill $skill, AddSkillLevel $add): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $add->handle($actor, $skill, $request->validated());

        return redirect()->route('skills.show', $skill)->with('status', 'Rating definition added.');
    }

    public function update(Request $request, SkillLevel $skillLevel, AddSkillLevel $add): RedirectResponse
    {
        Gate::authorize('manage-skills');
        $request->validate(['is_active' => ['required', 'declined'], 'ordinal' => ['prohibited'], 'label' => ['prohibited'], 'guidance' => ['prohibited']]);
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $add->retire($actor, $skillLevel);

        return redirect()->route('skills.show', $skillLevel->skill_id)->with('status', 'Rating definition retired; historical meaning preserved.');
    }
}
