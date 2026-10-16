<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\IssueAccountAccess;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AccountAccessController extends Controller
{
    public function store(Request $request, User $account, IssueAccountAccess $issue): Response
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $code = $issue->handle($actor, $account);

        return response()->view('accounts.access', ['account' => $account, 'code' => $code])
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
