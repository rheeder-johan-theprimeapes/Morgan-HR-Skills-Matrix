<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\IssueAccountAccess;
use App\Models\AccountAudit;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

#[Signature('app:bootstrap-account-admin {employee_number} {name}')]
#[Description('Create the first explicitly designated account administrator and print private setup access once')]
class BootstrapAccountAdmin extends Command
{
    public function handle(IssueAccountAccess $issue): int
    {
        $employeeNumber = $this->argument('employee_number');
        if (! is_string($employeeNumber)) {
            $this->error('Provide one employee ID.');

            return self::FAILURE;
        }
        $data = Validator::make([
            'employee_number' => Str::upper(trim($employeeNumber)),
            'name' => $this->argument('name'),
        ], [
            'employee_number' => ['required', 'string', 'max:40', 'regex:/\A[A-Z0-9][A-Z0-9._-]{0,39}\z/', 'unique:employees,employee_number', 'unique:users,login_id'],
            'name' => ['required', 'string', 'max:255'],
        ])->validate();
        try {
            $result = DB::transaction(function () use ($data, $issue): array {
                if (User::query()->where('can_administer_accounts', true)->lockForUpdate()->first() !== null) {
                    throw new LogicException('An account administrator already exists. Use the authorised administration flow.');
                }
                $employee = Employee::query()->create(['employee_number' => $data['employee_number'], 'name' => $data['name']]);
                $account = new User;
                $account->forceFill([
                    'employee_id' => $employee->id, 'login_id' => $data['employee_number'],
                    'name' => $data['name'], 'email' => null, 'password' => null,
                    'is_active' => true, 'can_administer_accounts' => true, 'session_version' => 1,
                ])->save();
                AccountAudit::query()->create(['actor_id' => $account->id, 'subject_id' => $account->id, 'action' => 'administrator.bootstrapped']);

                return ['account' => $account, 'code' => $issue->handle($account, $account)];
            }, 3);
        } catch (LogicException|ValidationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info('Initial administrator provisioned: '.$result['account']->login_id);
        $this->line('Private setup code (expires in 30 minutes): '.$result['code']);
        $this->line('Hand this code directly to the verified person. Do not save it in logs or tickets.');

        return self::SUCCESS;
    }
}
