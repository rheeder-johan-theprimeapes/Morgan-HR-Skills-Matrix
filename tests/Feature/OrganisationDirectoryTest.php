<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Employee;
use App\Models\JobRole;
use App\Models\User;
use App\Services\ReportingTree;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('requires authentication before directory access', function (): void {
    $this->get('/employees')->assertRedirect('/login');
});

it('lets authorised HR create catalogue records and an assigned employee', function (): void {
    $hr = User::factory()->directoryAdministrator()->create();
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->post('/departments', ['code' => 'PROD', 'name' => 'Production', 'is_active' => true])->assertRedirect();
    $this->post('/job-roles', ['code' => 'OPERATOR', 'name' => 'Operator', 'is_active' => true])->assertRedirect();
    $department = Department::query()->where('code', 'PROD')->firstOrFail();
    $role = JobRole::query()->where('code', 'OPERATOR')->firstOrFail();
    $manager = Employee::factory()->create();

    $this->post('/employees', [
        'employee_number' => 'prod-010', 'name' => 'Fictional Operator', 'department_id' => $department->id,
        'job_role_id' => $role->id, 'manager_id' => $manager->id, 'is_active' => true,
    ])->assertRedirect();

    $created = Employee::query()->where('employee_number', 'PROD-010')->firstOrFail();
    $this->assertDatabaseHas('employees', ['id' => $created->id, 'department_id' => $department->id, 'job_role_id' => $role->id, 'manager_id' => $manager->id]);
    $this->get('/employees/'.$created->id)->assertOk()->assertSee('Fictional Operator')->assertSee('Production');
});

it('does not confer directory authority through account administration', function (): void {
    $accountAdministrator = User::factory()->accountAdministrator()->create();
    $this->actingAs($accountAdministrator)->withSession(['account_session_version' => $accountAdministrator->session_version]);

    $outside = Employee::factory()->create(['name' => 'Outside Account Scope']);
    $this->get('/employees')->assertOk()->assertDontSee($outside->name);
    $this->get('/employees/'.$outside->id)->assertForbidden();
    $this->post('/employees', ['employee_number' => 'UNAUTH', 'name' => 'Unauthorised', 'is_active' => true])->assertForbidden();
    $this->assertDatabaseMissing('employees', ['employee_number' => 'UNAUTH']);
});

it('rejects a reciprocal reporting cycle without changing the employee', function (): void {
    $hr = User::factory()->directoryAdministrator()->create();
    $head = Employee::factory()->create();
    $middle = Employee::factory()->create(['manager_id' => $head->id]);
    $leaf = Employee::factory()->create(['manager_id' => $middle->id]);
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);

    $this->put('/employees/'.$head->id, ['name' => 'Attempted Change', 'manager_id' => $leaf->id, 'is_active' => true])
        ->assertSessionHasErrors('manager_id');
    expect($head->refresh()->manager_id)->toBeNull();
    expect($head->name)->not->toBe('Attempted Change');
});

it('rejects self-management', function (): void {
    $hr = User::factory()->directoryAdministrator()->create();
    $employee = Employee::factory()->create();
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);

    $this->put('/employees/'.$employee->id, ['name' => $employee->name, 'manager_id' => $employee->id, 'is_active' => true])
        ->assertSessionHasErrors('manager_id');
    expect($employee->refresh()->manager_id)->toBeNull();
});

it('keeps stable employee IDs immutable during ordinary edits', function (): void {
    $hr = User::factory()->directoryAdministrator()->create();
    $employee = Employee::factory()->create();
    $originalNumber = $employee->employee_number;
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);

    $this->put('/employees/'.$employee->id, ['employee_number' => 'CHANGED', 'name' => $employee->name, 'is_active' => true])
        ->assertSessionHasErrors('employee_number');
    expect($employee->refresh()->employee_number)->toBe($originalNumber);
});

it('refuses new assignments to retired catalogue records or managers', function (): void {
    $hr = User::factory()->directoryAdministrator()->create();
    $department = Department::factory()->create(['is_active' => false]);
    $manager = Employee::factory()->create(['is_active' => false]);
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);

    $this->post('/employees', ['employee_number' => 'NEW-010', 'name' => 'Fictional New', 'department_id' => $department->id, 'manager_id' => $manager->id, 'is_active' => true])
        ->assertSessionHasErrors(['department_id', 'manager_id']);
    $this->assertDatabaseMissing('employees', ['employee_number' => 'NEW-010']);
});

it('retires a person while preserving their account and revoking access', function (): void {
    $hr = User::factory()->directoryAdministrator()->create();
    $account = User::factory()->withEmployee()->create();
    $person = $account->employee;
    $version = $account->session_version;
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);

    $this->put('/employees/'.$person->id, ['name' => $person->name, 'is_active' => false])->assertRedirect();
    $this->assertModelExists($person);
    $this->assertModelExists($account);
    expect($person->refresh()->is_active)->toBeFalse();
    expect($account->refresh()->is_active)->toBeFalse();
    expect($account->session_version)->toBe($version + 1);
});

it('requires active reports to be reassigned before retiring a manager', function (): void {
    $hr = User::factory()->directoryAdministrator()->create();
    $manager = Employee::factory()->create();
    Employee::factory()->create(['manager_id' => $manager->id]);
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);

    $this->put('/employees/'.$manager->id, ['name' => $manager->name, 'is_active' => false])->assertSessionHasErrors('is_active');
    expect($manager->refresh()->is_active)->toBeTrue();
});

it('resolves all direct and indirect reporting descendants with stable ordering', function (): void {
    $manager = Employee::factory()->create();
    $direct = Employee::factory()->create(['manager_id' => $manager->id]);
    $indirect = Employee::factory()->create(['manager_id' => $direct->id]);
    Employee::factory()->create();

    expect(app(ReportingTree::class)->descendantIds($manager))->toBe([$direct->id, $indirect->id]);
});

it('links only the intended unlinked account and requires fresh setup', function (): void {
    $hr = User::factory()->directoryAdministrator()->accountAdministrator()->create();
    $person = Employee::factory()->create();
    $unlinked = User::factory()->create();
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);

    $this->post('/employees/'.$person->id.'/account-link', ['account_id' => $unlinked->id])->assertRedirect();
    expect($unlinked->refresh()->employee_id)->toBe($person->id);
    expect($unlinked->login_id)->toBe($person->employee_number);
    expect($unlinked->password)->toBeNull();
});

it('rejects conflicting account links atomically', function (): void {
    $hr = User::factory()->directoryAdministrator()->accountAdministrator()->create();
    $person = Employee::factory()->create();
    $alreadyLinked = User::factory()->withEmployee()->create();
    $originalPerson = $alreadyLinked->employee_id;
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);

    $this->post('/employees/'.$person->id.'/account-link', ['account_id' => $alreadyLinked->id])->assertSessionHasErrors('account_id');
    expect($alreadyLinked->refresh()->employee_id)->toBe($originalPerson);
    expect($person->user)->toBeNull();
});

it('provisions access for an existing person without duplicating their stable record', function (): void {
    $hr = User::factory()->directoryAdministrator()->accountAdministrator()->create();
    $person = Employee::factory()->create();
    $peopleBefore = Employee::query()->count();
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);

    $this->post('/employees/'.$person->id.'/account')->assertRedirect();
    expect(Employee::query()->count())->toBe($peopleBefore);
    $this->assertDatabaseHas('users', ['employee_id' => $person->id, 'login_id' => $person->employee_number, 'password' => null]);
    $this->post('/employees/'.$person->id.'/account')->assertSessionHasErrors('account');
});

it('requires active employees to be reassigned before retiring a department', function (): void {
    $hr = User::factory()->directoryAdministrator()->create();
    $department = Department::factory()->create();
    Employee::factory()->create(['department_id' => $department->id]);
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);

    $this->put('/departments/'.$department->id, ['name' => $department->name, 'is_active' => false])->assertSessionHasErrors('is_active');
    expect($department->refresh()->is_active)->toBeTrue();
});

it('preserves existing retired assignments when correcting a retired person', function (): void {
    $hr = User::factory()->directoryAdministrator()->create();
    $department = Department::factory()->create();
    $role = JobRole::factory()->create();
    $manager = Employee::factory()->create();
    $person = Employee::factory()->create(['department_id' => $department->id, 'job_role_id' => $role->id, 'manager_id' => $manager->id]);
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $data = ['name' => $person->name, 'department_id' => $department->id, 'job_role_id' => $role->id, 'manager_id' => $manager->id, 'is_active' => false];
    $this->put('/employees/'.$person->id, $data)->assertRedirect()->assertSessionHasNoErrors();
    $this->put('/departments/'.$department->id, ['name' => $department->name, 'is_active' => false])->assertRedirect()->assertSessionHasNoErrors();
    $this->put('/job-roles/'.$role->id, ['name' => $role->name, 'is_active' => false])->assertRedirect()->assertSessionHasNoErrors();
    $this->put('/employees/'.$manager->id, ['name' => $manager->name, 'is_active' => false])->assertRedirect()->assertSessionHasNoErrors();
    $this->get('/employees/'.$person->id.'/edit')->assertOk()->assertSee($department->name)->assertSee($role->name)->assertSee($manager->name);
    $this->put('/employees/'.$person->id, array_replace($data, ['name' => 'Corrected Retired Name']))->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('employees', ['id' => $person->id, 'name' => 'Corrected Retired Name', 'department_id' => $department->id, 'job_role_id' => $role->id, 'manager_id' => $manager->id, 'is_active' => false]);
    $this->put('/employees/'.$person->id, array_replace($data, ['is_active' => true]))->assertSessionHasErrors(['department_id', 'job_role_id', 'manager_id']);
    expect($person->refresh()->is_active)->toBeFalse();
});
