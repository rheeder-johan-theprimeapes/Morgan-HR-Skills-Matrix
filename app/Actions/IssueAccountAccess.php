<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AccountAccessToken;
use App\Models\AccountAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IssueAccountAccess
{
    public function handle(User $actor, User $subject): string
    {
        return DB::transaction(function () use ($actor, $subject): string {
            $currentActor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $account = User::query()->lockForUpdate()->findOrFail($subject->id);
            Gate::forUser($currentActor)->authorize('issueAccess', $account);
            if (! $account->is_active || ! $account->employee?->is_active) {
                throw ValidationException::withMessages(['account' => 'Setup access is unavailable for this account.']);
            }
            $code = Str::random(64);
            AccountAccessToken::query()->updateOrCreate(['user_id' => $account->id], [
                'token_hash' => hash('sha256', $code), 'expires_at' => now()->addMinutes(30), 'used_at' => null,
            ]);
            $account->forceFill(['password' => null, 'remember_token' => Str::random(60), 'session_version' => $account->session_version + 1])->save();
            AccountAudit::query()->create(['actor_id' => $currentActor->id, 'subject_id' => $account->id, 'action' => 'access.issued']);

            return $code;
        }, 3);
    }
}
