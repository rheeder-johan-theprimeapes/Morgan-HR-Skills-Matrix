<?php

declare(strict_types=1);

use App\Capability;
use App\Models\AuditEvent;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobRole;
use App\Models\User;
use App\Services\EmployeeAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('limits employee lists and direct profiles to the current self scope', function (): void {
    $employee = User::factory()->withEmployee()->create();
    $outsider = Employee::factory()->create(['name' => 'OutsideScopeUnique']);
    $this->actingAs($employee)->withSession(['account_session_version' => $employee->session_version]);

    $this->get('/employees')->assertOk()->assertSee($employee->employee->name)->assertDontSee($outsider->name)->assertDontSee('Add employee')->assertDontSee('Directory administration');
    $this->get('/employees/'.$employee->employee_id)->assertOk()->assertDontSee('Edit employee')->assertDontSee('Account access');
    $this->get('/employees/'.$outsider->id)->assertForbidden();
    $this->get('/employees/'.$employee->employee_id.'/edit')->assertForbidden();
});

it('includes direct and indirect reports without granting department access', function (): void {
    $department = Department::factory()->create();
    $manager = User::factory()->withEmployee()->create();
    $manager->employee->forceFill(['department_id' => $department->id])->save();
    $direct = Employee::factory()->create(['manager_id' => $manager->employee_id]);
    $indirect = Employee::factory()->create(['manager_id' => $direct->id]);
    $colleague = Employee::factory()->create(['department_id' => $department->id]);
    $this->actingAs($manager)->withSession(['account_session_version' => $manager->session_version]);

    $this->get('/employees')->assertOk()->assertSee($direct->name)->assertSee($indirect->name)->assertDontSee($colleague->name);
    $this->get('/employees/'.$indirect->id)->assertOk();
    $this->get('/employees/'.$colleague->id)->assertForbidden();
    $this->put('/employees/'.$direct->id, ['name' => 'Forged', 'is_active' => true])->assertForbidden();
});

it('applies reporting reassignment to an already authenticated manager', function (): void {
    $manager = User::factory()->withEmployee()->create();
    $report = Employee::factory()->create(['manager_id' => $manager->employee_id]);
    $this->actingAs($manager)->withSession(['account_session_version' => $manager->session_version]);
    $this->get('/employees/'.$report->id)->assertOk();

    $report->forceFill(['manager_id' => null])->save();
    $this->get('/employees/'.$report->id)->assertForbidden();
});

it('grants organisation reading independently of directory writes and pay powers', function (): void {
    $slt = User::factory()->withEmployee()->create(['capabilities' => [Capability::ViewOrganisation->value, Capability::DecideReviews->value]]);
    $person = Employee::factory()->create();
    $this->actingAs($slt)->withSession(['account_session_version' => $slt->session_version]);

    $this->get('/employees/'.$person->id)->assertOk()->assertDontSee('Edit employee');
    $this->get('/departments')->assertForbidden();
    expect($slt->hasCapability(Capability::DecideReviews))->toBeTrue();
    foreach ([Capability::ActualPay, Capability::Benchmarks, Capability::RecommendPay, Capability::ApprovePay] as $capability) {
        expect($slt->hasCapability($capability))->toBeFalse();
    }
});

it('keeps every confidential capability independent even for account and directory administrators', function (): void {
    $administrator = User::factory()->directoryAdministrator()->accountAdministrator()->create();
    expect($administrator->hasCapability(Capability::ManageDirectory))->toBeTrue();
    expect($administrator->hasCapability(Capability::AdministerAccounts))->toBeTrue();
    foreach ([Capability::ActualPay, Capability::Benchmarks, Capability::RecommendPay, Capability::ApprovePay, Capability::DecideReviews] as $capability) {
        expect($administrator->hasCapability($capability))->toBeFalse();
    }
    $administrator->forceFill(['capabilities' => [Capability::ActualPay->value]])->save();
    expect($administrator->fresh()->hasCapability(Capability::ActualPay))->toBeTrue();
    expect($administrator->fresh()->hasCapability(Capability::Benchmarks))->toBeFalse();
});

it('returns only scalar allowlisted profile fields to the view', function (): void {
    $employee = User::factory()->withEmployee()->create(['email' => 'private@example.test']);
    $this->actingAs($employee)->withSession(['account_session_version' => $employee->session_version]);
    $response = $this->get('/employees/'.$employee->employee_id)->assertOk()->assertDontSee($employee->email);
    $profile = $response->viewData('profile');
    expect(array_keys($profile))->toBe(['id', 'employee_number', 'name', 'department', 'job_role', 'manager', 'is_active']);
    expect($response->viewData('linkedAccount'))->toBeNull();
    expect($response->viewData('unlinkedAccounts'))->toBe([]);
});

it('ignores stale loaded organisation grants in reusable queries', function (): void {
    $reader = User::factory()->withEmployee()->create(['capabilities' => [Capability::ViewOrganisation->value]]);
    $person = Employee::factory()->create();
    expect(app(EmployeeAccess::class)->query($reader)->whereKey($person->id)->exists())->toBeTrue();
    User::query()->whereKey($reader->id)->update(['capabilities' => '[]']);
    expect(app(EmployeeAccess::class)->query($reader)->whereKey($person->id)->exists())->toBeFalse();
});

it('fails closed for a corrupted reporting cycle', function (): void {
    $manager = User::factory()->withEmployee()->create();
    $report = Employee::factory()->create(['manager_id' => $manager->employee_id]);
    $manager->employee->forceFill(['manager_id' => $report->id])->save();
    expect(app(EmployeeAccess::class)->query($manager)->pluck('id')->all())->toBe([$manager->employee_id]);
});

it('changes one explicitly named capability from the trusted console and audits it', function (): void {
    $account = User::factory()->withEmployee()->create();
    $version = $account->session_version;
    $this->artisan('app:set-account-capability', ['employee_number' => $account->employee->employee_number, 'capability' => Capability::ManageDirectory->value, '--reason' => 'Fictional HR designation'])->assertExitCode(0);
    expect($account->refresh()->hasCapability(Capability::ManageDirectory))->toBeTrue();
    expect($account->session_version)->toBe($version + 1);
    expect($account->hasCapability(Capability::ActualPay))->toBeFalse();
    $this->assertDatabaseHas('audit_events', ['event' => 'capability.granted', 'subject_id' => $account->id]);
    $details = AuditEvent::query()->firstOrFail()->details;
    ksort($details);
    expect($details)->toBe(['capability' => Capability::ManageDirectory->value, 'reason' => 'Fictional HR designation']);
    $this->artisan('app:set-account-capability', ['employee_number' => $account->employee->employee_number, 'capability' => Capability::ManageDirectory->value, '--reason' => 'Fictional revocation', '--revoke' => true])->assertExitCode(0);
    expect($account->refresh()->hasCapability(Capability::ManageDirectory))->toBeFalse();
    $this->assertDatabaseHas('audit_events', ['event' => 'capability.revoked']);
});

it('refuses unknown grants and grants without a recorded reason', function (): void {
    $account = User::factory()->withEmployee()->create();
    $this->artisan('app:set-account-capability', ['employee_number' => $account->employee->employee_number, 'capability' => 'superuser', '--reason' => 'Example'])->assertExitCode(1);
    $this->artisan('app:set-account-capability', ['employee_number' => $account->employee->employee_number, 'capability' => Capability::ActualPay->value])->assertExitCode(1);
    expect(AuditEvent::query()->count())->toBe(0);
});

it('audits directory changes without storing passwords or access codes', function (): void {
    $hr = User::factory()->directoryAdministrator()->create();
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->post('/employees', ['employee_number' => 'AUDIT-01', 'name' => 'Fictional Audit', 'is_active' => true])->assertRedirect();
    $event = AuditEvent::query()->where('event', 'directory.employee.saved')->firstOrFail();
    expect($event->actor_id)->toBe($hr->id);
    $details = $event->details;
    ksort($details);
    expect($details)->toBe(['employee_number' => 'AUDIT-01', 'is_active' => true]);
});

it('revokes an existing session after a capability grant changes', function (): void {
    $account = User::factory()->withEmployee()->create();
    $this->actingAs($account)->withSession(['account_session_version' => $account->session_version]);
    $this->get('/dashboard')->assertOk();
    $this->artisan('app:set-account-capability', ['employee_number' => $account->employee->employee_number, 'capability' => Capability::ActualPay->value, '--reason' => 'Fictional designation'])->assertExitCode(0);
    $this->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();
});

it('cannot inject capabilities through a directory update', function (): void {
    $hr = User::factory()->directoryAdministrator()->create();
    $account = User::factory()->withEmployee()->create();
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->put('/employees/'.$account->employee_id, ['name' => 'Safe Name', 'is_active' => true, 'capabilities' => [Capability::ApprovePay->value], 'can_administer_accounts' => true])->assertRedirect();
    expect($account->refresh()->hasCapability(Capability::ApprovePay))->toBeFalse();
    expect($account->can_administer_accounts)->toBeFalse();
});

it('renders authorised directory forms and account controls through ordinary navigation', function (): void {
    $hr = User::factory()->directoryAdministrator()->accountAdministrator()->create();
    $department = Department::factory()->create();
    $role = JobRole::factory()->create();
    $account = User::factory()->withEmployee()->create();
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    foreach (['/employees/create', '/employees/'.$account->employee_id.'/edit', '/departments', '/departments/'.$department->id.'/edit', '/job-roles', '/job-roles/'.$role->id.'/edit'] as $url) {
        $this->get($url)->assertOk();
    }
    $this->get('/employees/'.$account->employee_id)->assertOk()->assertSee('Manage setup or recovery')->assertViewHas('linkedAccount', ['id' => $account->id, 'login_id' => $account->login_id]);
});
