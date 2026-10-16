<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SaveOrganisationRecord;
use App\Http\Requests\SaveJobRoleRequest;
use App\Models\JobRole;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class JobRoleController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-directory');

        return view('directory.catalogue', ['records' => JobRole::query()->orderBy('name')->orderBy('id')->paginate(25), 'routePrefix' => 'job-roles', 'title' => 'Job roles']);
    }

    public function store(SaveJobRoleRequest $request, SaveOrganisationRecord $save): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $save->handle($actor, new JobRole, $request->recordData());

        return redirect()->route('job-roles.index')->with('status', 'Record created.');
    }

    public function edit(JobRole $jobRole): View
    {
        Gate::authorize('manage-directory');

        return view('directory.record', ['record' => $jobRole, 'routePrefix' => 'job-roles', 'title' => 'Job role']);
    }

    public function update(SaveJobRoleRequest $request, JobRole $jobRole, SaveOrganisationRecord $save): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $save->handle($actor, $jobRole, $request->recordData());

        return redirect()->route('job-roles.index')->with('status', 'Record updated.');
    }
}
