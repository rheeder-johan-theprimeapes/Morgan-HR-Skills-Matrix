<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CompleteAccountAccess;
use App\Http\Requests\CompleteAccountAccessRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class AccountSetupController extends Controller
{
    public function create(): View
    {
        return view('auth.setup');
    }

    public function store(CompleteAccountAccessRequest $request, CompleteAccountAccess $complete): RedirectResponse
    {
        $complete->handle($request->string('login_id')->toString(), $request->string('code')->toString(), $request->string('password')->toString());

        return redirect()->route('login')->with('status', 'Your password is ready. You can now sign in.');
    }
}
