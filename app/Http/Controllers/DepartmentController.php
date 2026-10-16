<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SaveOrganisationRecord;
use App\Http\Requests\SaveDepartmentRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class DepartmentController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-directory');

        return view('directory.catalogue', ['records' => Department::query()->orderBy('name')->orderBy('id')->paginate(25), 'routePrefix' => 'departments', 'title' => 'Departments']);
    }

    public function store(SaveDepartmentRequest $request, SaveOrganisationRecord $save): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $save->handle($actor, new Department, $request->recordData());

        return redirect()->route('departments.index')->with('status', 'Record created.');
    }

    public function edit(Department $department): View
    {
        Gate::authorize('manage-directory');

        return view('directory.record', ['record' => $department, 'routePrefix' => 'departments', 'title' => 'Department']);
    }

    public function update(SaveDepartmentRequest $request, Department $department, SaveOrganisationRecord $save): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $save->handle($actor, $department, $request->recordData());

        return redirect()->route('departments.index')->with('status', 'Record updated.');
    }
}
