<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\LinkEmployeeAccount;
use App\Actions\ProvisionAccount;
use App\Http\Requests\LinkEmployeeAccountRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class EmployeeAccountController extends Controller
{
    public function store(Request $request, Employee $employee, ProvisionAccount $provision): RedirectResponse
    {
        Gate::authorize('linkAccount', $employee);
        $request->validate(['email' => ['nullable', 'email', 'max:255', 'unique:users,email']]);
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $account = $provision->forEmployee($actor, $employee, $request->filled('email') ? Str::lower($request->string('email')->toString()) : null);

        return redirect()->route('accounts.show', $account)->with('status', 'Account provisioned for the existing employee. Issue private setup access after verifying identity.');
    }

    public function link(LinkEmployeeAccountRequest $request, Employee $employee, LinkEmployeeAccount $link): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $link->handle($actor, $employee, User::query()->findOrFail($request->integer('account_id')));

        return redirect()->route('employees.show', $employee)->with('status', 'Account linked. Fresh setup access is required.');
    }
}
