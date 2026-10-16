<?php

declare(strict_types=1);

use App\Actions\SaveEmployee;
use App\Actions\SaveOrganisationRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(DatabaseTruncation::class);

it('refuses a department retired after the transaction establishes an older snapshot', function (): void {
    $hr = User::factory()->directoryAdministrator()->create();
    $department = Department::factory()->create();
    $settings = config('database.connections.mysql');
    expect(app()->environment())->toBe('testing');
    expect($settings['database'])->toBe('morgan_hr_skills_test');
    $other = DB::build($settings);
    expect($other->selectOne('SELECT DATABASE() AS name')->name)->toBe('morgan_hr_skills_test');
    $interleaved = false;
    DB::listen(function (QueryExecuted $query) use ($other, $department, &$interleaved): void {
        if (! $interleaved && DB::connection()->transactionLevel() > 0 && str_contains($query->sql, 'from `users`')) {
            $interleaved = true;
            $other->table('departments')->where('id', $department->id)->update(['is_active' => false]);
        }
    });
    expect(fn () => app(SaveEmployee::class)->handle($hr, null, [
        'employee_number' => 'SNAPSHOT-01', 'name' => 'Fictional Snapshot', 'department_id' => $department->id,
        'job_role_id' => null, 'manager_id' => null, 'is_active' => true,
    ]))->toThrow(ValidationException::class);
    expect($interleaved)->toBeTrue();
    $this->assertDatabaseMissing('employees', ['employee_number' => 'SNAPSHOT-01']);
});

it('refuses catalogue retirement after a new active assignment commits beyond an older snapshot', function (): void {
    $hr = User::factory()->directoryAdministrator()->create();
    $department = Department::factory()->create();
    $person = Employee::factory()->create();
    $settings = config('database.connections.mysql');
    expect(app()->environment())->toBe('testing');
    expect($settings['database'])->toBe('morgan_hr_skills_test');
    $other = DB::build($settings);
    expect($other->selectOne('SELECT DATABASE() AS name')->name)->toBe('morgan_hr_skills_test');
    $interleaved = false;
    DB::listen(function (QueryExecuted $query) use ($other, $person, $department, &$interleaved): void {
        if (! $interleaved && DB::connection()->transactionLevel() > 0 && str_contains($query->sql, 'from `users`')) {
            $interleaved = true;
            $other->table('employees')->where('id', $person->id)->update(['department_id' => $department->id]);
        }
    });
    expect(fn () => app(SaveOrganisationRecord::class)->handle($hr, $department, ['code' => '', 'name' => $department->name, 'is_active' => false]))->toThrow(ValidationException::class);
    expect($interleaved)->toBeTrue();
    expect($department->refresh()->is_active)->toBeTrue();
});
