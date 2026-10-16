<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Capability;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:set-account-capability {employee_number} {capability} {--revoke} {--reason=}')]
#[Description('Grant or revoke one explicit capability using trusted local console access and a recorded reason')]
class SetAccountCapability extends Command
{
    public function handle(): int
    {
        $number = $this->argument('employee_number');
        $value = $this->argument('capability');
        $reason = $this->option('reason');
        $capability = is_string($value) ? Capability::tryFrom($value) : null;
        if (! is_string($number) || $capability === null || ! is_string($reason) || trim($reason) === '' || strlen($reason) > 500) {
            $this->error('Provide a valid employee ID, named capability and reason (maximum 500 bytes).');

            return self::FAILURE;
        }
        $granted = ! $this->option('revoke');
        $changed = DB::transaction(function () use ($number, $capability, $reason, $granted): bool {
            $account = User::query()->whereHas('employee', fn ($query) => $query->where('employee_number', strtoupper(trim($number)))->where('is_active', true))->where('is_active', true)->lockForUpdate()->first();
            if ($account === null) {
                return false;
            }
            if ($capability === Capability::AdministerAccounts) {
                $account->can_administer_accounts = $granted;
            } elseif ($capability === Capability::ManageDirectory) {
                $account->can_manage_directory = $granted;
            } else {
                $values = array_values(array_filter($account->capabilities ?? [], fn (string $existing): bool => $existing !== $capability->value));
                if ($granted) {
                    $values[] = $capability->value;
                }
                sort($values);
                $account->capabilities = $values;
            }
            $account->session_version++;
            $account->save();
            AuditEvent::query()->create([
                'actor_id' => null, 'event' => $granted ? 'capability.granted' : 'capability.revoked',
                'subject_type' => 'user', 'subject_id' => $account->id,
                'details' => ['capability' => $capability->value, 'reason' => trim($reason)],
            ]);

            return true;
        }, 3);
        if (! $changed) {
            $this->error('No active linked account matches that employee ID.');

            return self::FAILURE;
        }
        $this->info('Capability updated; existing sessions have been revoked.');

        return self::SUCCESS;
    }
}
