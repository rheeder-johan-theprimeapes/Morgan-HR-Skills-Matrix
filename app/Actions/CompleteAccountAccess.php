<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AccountAccessToken;
use App\Models\AccountAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompleteAccountAccess
{
    public function handle(string $loginId, string $code, string $password): void
    {
        DB::transaction(function () use ($loginId, $code, $password): void {
            $account = User::query()->where('login_id', $loginId)->lockForUpdate()->first();
            $token = $account === null ? null : AccountAccessToken::query()->where('user_id', $account->id)->lockForUpdate()->first();
            if ($account === null || ! $account->is_active || ! $account->employee?->is_active
                || $token === null || $token->used_at !== null || $token->expires_at->lessThanOrEqualTo(now())
                || ! hash_equals($token->token_hash, hash('sha256', $code))) {
                throw ValidationException::withMessages(['code' => 'Setup access is invalid or has expired. Contact HR.']);
            }
            $account->forceFill(['password' => $password, 'remember_token' => Str::random(60), 'session_version' => $account->session_version + 1])->save();
            $token->forceFill(['used_at' => now()])->save();
            AccountAudit::query()->create(['actor_id' => $account->id, 'subject_id' => $account->id, 'action' => 'access.used']);
        }, 3);
    }
}
