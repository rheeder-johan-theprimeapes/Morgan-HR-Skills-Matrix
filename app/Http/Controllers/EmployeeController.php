<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SaveEmployee;
use App\Http\Requests\SaveEmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobRole;
use App\Models\User;
use App\Services\EmployeeAccess;
use App\Services\EmployeeProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EmployeeController extends Controller
{
    public function index(Request $request, EmployeeAccess $access): View
    {
        Gate::authorize('viewAny', Employee::class);
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $employees = $access->query($actor)->with(['department', 'jobRole', 'manager'])->orderBy('name')->orderBy('id')->paginate(25);
        $employees->through(fn (Employee $person): array => EmployeeProfile::fromEmployee($person));

        return view('employees.index', ['employees' => $employees]);
    }

    public function create(): View
    {
        Gate::authorize('create', Employee::class);

        return view('employees.form', $this->formData(new Employee));
    }

    public function store(SaveEmployeeRequest $request, SaveEmployee $save): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $employee = $save->handle($actor, null, $request->employeeData());

        return redirect()->route('employees.show', $employee)->with('status', 'Employee created.');
    }

    public function show(Employee $employee): View
    {
        Gate::authorize('view', $employee);
        $canLink = Gate::allows('linkAccount', $employee);
        $linked = null;
        $unlinked = [];
        if ($canLink) {
            $account = $employee->user;
            $linked = $account === null ? null : ['id' => $account->id, 'login_id' => $account->login_id];
            $unlinked = User::query()->whereNull('employee_id')->orderBy('name')->orderBy('id')->get(['id', 'name', 'login_id'])->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name, 'login_id' => $user->login_id])->all();
        }

        return view('employees.show', [
            'profile' => EmployeeProfile::fromEmployee($employee->load(['department', 'jobRole', 'manager'])),
            'canDocuments' => Gate::allows('viewHistoricalDocuments', $employee), 'canContext' => Gate::allows('viewOperationalContext', $employee), 'canPay' => Gate::allows('viewPay', $employee), 'canScore' => Gate::allows('viewScore', $employee), 'canAssess' => Gate::allows('viewAssessments', $employee), 'canEdit' => Gate::allows('update', $employee), 'canLink' => $canLink,
            'linkedAccount' => $linked, 'unlinkedAccounts' => $unlinked,
        ]);
    }

    public function edit(Employee $employee): View
    {
        Gate::authorize('update', $employee);

        return view('employees.form', $this->formData($employee));
    }

    public function update(SaveEmployeeRequest $request, Employee $employee, SaveEmployee $save): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $save->handle($actor, $employee, $request->employeeData());

        return redirect()->route('employees.show', $employee)->with('status', 'Employee updated.');
    }

    /** @return array{employee: Employee, departments: Collection<int, Department>, jobRoles: Collection<int, JobRole>, managers: Collection<int, Employee>} */
    private function formData(Employee $employee): array
    {
        return [
            'employee' => $employee,
            'departments' => Department::query()->where('is_active', true)->orWhereKey($employee->department_id)->orderBy('name')->orderBy('id')->get(),
            'jobRoles' => JobRole::query()->where('is_active', true)->orWhereKey($employee->job_role_id)->orderBy('name')->orderBy('id')->get(),
            'managers' => Employee::query()->where('is_active', true)->orWhereKey($employee->manager_id)->orderBy('name')->orderBy('id')->get(),
        ];
    }
}
