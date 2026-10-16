<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SaveFramework;
use App\Http\Requests\SaveFrameworkRequest;
use App\Models\ScoringFramework;
use App\Models\User;
use App\Services\FrameworkDefinition;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FrameworkController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-skills');

        return view('frameworks.index', ['frameworks' => ScoringFramework::query()->latest('id')->paginate(20)]);
    }

    public function create(FrameworkDefinition $definitions): View
    {
        Gate::authorize('manage-skills');

        return view('frameworks.form', ['framework' => new ScoringFramework, 'editor' => $definitions->editor(null)]);
    }

    public function store(SaveFrameworkRequest $request, SaveFramework $save): RedirectResponse
    {
        $framework = $save->handle($this->actor($request), null, $request->validated());

        return redirect()->route('frameworks.show', $framework)->with('status', 'Framework draft saved.');
    }

    public function show(ScoringFramework $framework): View
    {
        Gate::authorize('manage-skills');

        return view('frameworks.show', ['framework' => $framework]);
    }

    public function edit(ScoringFramework $framework, FrameworkDefinition $definitions): View
    {
        Gate::authorize('manage-skills');
        abort_if($framework->published_at !== null, 409, 'Create a successor to change this published framework.');

        return view('frameworks.form', ['framework' => $framework, 'editor' => $definitions->editor($framework)]);
    }

    public function update(SaveFrameworkRequest $request, ScoringFramework $framework, SaveFramework $save): RedirectResponse
    {
        $save->handle($this->actor($request), $framework, $request->validated());

        return redirect()->route('frameworks.show', $framework)->with('status', 'Framework draft saved.');
    }

    public function publish(Request $request, ScoringFramework $framework, SaveFramework $save): RedirectResponse
    {
        Gate::authorize('manage-skills');
        $values = $request->validate(['expected_revision' => ['required', 'integer', 'min:1']]);
        $save->publish($this->actor($request), $framework, (int) $values['expected_revision']);

        return redirect()->route('frameworks.show', $framework)->with('status', 'Framework version published.');
    }

    public function successor(Request $request, ScoringFramework $framework, SaveFramework $save): RedirectResponse
    {
        Gate::authorize('manage-skills');
        $next = $save->successor($this->actor($request), $framework);

        return redirect()->route('frameworks.edit', $next)->with('status', 'Independent successor draft created. Review policy and approval before publication.');
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
