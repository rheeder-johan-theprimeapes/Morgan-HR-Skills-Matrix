<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ProvisionAccount;
use App\Http\Requests\ProvisionAccountRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class AccountController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        return view('accounts.index', ['accounts' => User::query()->with('employee')->orderBy('login_id')->orderBy('id')->paginate(20)]);
    }

    public function store(ProvisionAccountRequest $request, ProvisionAccount $provision): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $account = $provision->handle($actor, [
            'employee_number' => $request->string('employee_number')->toString(),
            'name' => $request->string('name')->toString(),
            'email' => $request->filled('email') ? $request->string('email')->toString() : null,
        ]);

        return redirect()->route('accounts.show', $account)->with('status', 'Account provisioned. Verify identity before issuing setup access.');
    }

    public function show(User $account): View
    {
        Gate::authorize('view', $account);

        return view('accounts.show', ['account' => $account->load('employee')]);
    }
}
