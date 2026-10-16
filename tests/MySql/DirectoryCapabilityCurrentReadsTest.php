<?php

declare(strict_types=1);
use App\Actions\LinkEmployeeAccount;
use App\Actions\SaveEmployee;
use App\Actions\SaveOrganisationRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;

uses(DatabaseTruncation::class);
it('rejects directory authority revoked beyond an older caller snapshot', function (string $operation): void {
    $actor = User::factory()->directoryAdministrator()->accountAdministrator()->create();
    $person = Employee::factory()->create();
    $unlinked = User::factory()->create();
    $settings = config('database.connections.mysql');
    expect(app()->environment())->toBe('testing');
    expect($settings['database'])->toBe('morgan_hr_skills_test');
    $other = DB::build($settings);
    expect($other->selectOne('SELECT DATABASE() AS name')->name)->toBe('morgan_hr_skills_test');
    DB::transaction(function () use ($actor, $person, $unlinked, $other, $operation): void {
        expect(User::query()->findOrFail($actor->id)->canManageDirectory())->toBeTrue();
        $other->table('users')->where('id', $actor->id)->update(['can_manage_directory' => false]);
        $write = fn () => match ($operation) {
            'employee' => app(SaveEmployee::class)->handle($actor, null, ['employee_number' => 'DIR-CAP-CREATE', 'name' => 'Denied person', 'department_id' => null, 'job_role_id' => null, 'manager_id' => null, 'is_active' => true]),
            'catalogue' => app(SaveOrganisationRecord::class)->handle($actor, new Department, ['code' => 'DIR-CAP-CREATE', 'name' => 'Denied department', 'is_active' => true]),
            'link' => app(LinkEmployeeAccount::class)->handle($actor, $person, $unlinked),
        };
        expect($write)->toThrow(AuthorizationException::class);
    });
    $this->assertDatabaseMissing('employees', ['employee_number' => 'DIR-CAP-CREATE']);
    $this->assertDatabaseMissing('departments', ['code' => 'DIR-CAP-CREATE']);
    expect($unlinked->fresh()->employee_id)->toBeNull();
    $this->assertDatabaseCount('account_audits', 0);
})->with(['employee', 'catalogue', 'link']);
