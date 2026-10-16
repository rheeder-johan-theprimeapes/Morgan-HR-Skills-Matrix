<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SignInRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(SignInRequest $request): RedirectResponse
    {
        if (! Auth::attemptWhen([
            'login_id' => $request->string('login_id')->toString(),
            'password' => $request->string('password')->toString(),
        ], fn (User $user): bool => $user->hasNativeAccess())) {
            throw ValidationException::withMessages(['login_id' => 'The sign-in details could not be verified.']);
        }
        $account = Auth::user();
        if (! $account instanceof User) {
            abort(403);
        }
        $request->session()->regenerate();
        $request->session()->put('account_session_version', $account->session_version);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
