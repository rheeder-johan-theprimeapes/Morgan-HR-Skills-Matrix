<?php

declare(strict_types=1);

use App\Capability;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobRole;
use App\Models\Skill;
use App\Models\SkillLevel;
use App\Models\SkillTarget;
use App\Models\User;
use App\Services\TargetResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('shows explicit missing targets on a permitted employee page', function (): void {
    $account = User::factory()->withEmployee()->create();
    Skill::factory()->create(['name' => 'Illustrative required skill']);
    $this->actingAs($account)->withSession(['account_session_version' => $account->session_version]);
    $this->get('/employees/'.$account->employee_id.'/targets')->assertOk()->assertSee('Illustrative required skill')->assertSee('No target');
});

it('lets HR approve a lower individual exception with visible provenance', function (): void {
    $hr = User::factory()->withEmployee()->create(['capabilities' => [Capability::ManageFrameworks->value, Capability::ViewOrganisation->value]]);
    $account = User::factory()->withEmployee()->create();
    $department = Department::factory()->create();
    $account->employee->forceFill(['department_id' => $department->id])->save();
    $skill = Skill::factory()->create();
    $high = SkillLevel::factory()->create(['skill_id' => $skill->id, 'ordinal' => 4]);
    $low = SkillLevel::factory()->create(['skill_id' => $skill->id, 'ordinal' => 1]);
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->post('/skills/'.$skill->id.'/targets', ['scope' => 'department', 'subject_id' => $department->id, 'skill_level_id' => $high->id, 'is_active' => true])->assertRedirect()->assertSessionHasNoErrors();
    $this->post('/skills/'.$skill->id.'/targets', ['scope' => 'employee', 'subject_id' => $account->employee_id, 'skill_level_id' => $low->id, 'reason' => 'Illustrative supported onboarding exception', 'is_active' => true, 'approved_by' => $account->id])->assertRedirect()->assertSessionHasNoErrors();
    $resolved = app(TargetResolver::class)->resolve($account->employee->fresh(), $skill);
    expect($resolved->ordinal)->toBe(1);
    expect($resolved->source)->toBe('HR-approved individual exception');
    expect($resolved->exceptionReason)->toBe('Illustrative supported onboarding exception');
    expect(SkillTarget::query()->where('employee_id', $account->employee_id)->firstOrFail()->approved_by)->toBe($hr->id);
    $this->get('/skills/'.$skill->id.'/targets')->assertOk()->assertSee('Target level');
});

it('uses the higher role or department target and preserves both sources on a tie', function (): void {
    $department = Department::factory()->create();
    $role = JobRole::factory()->create();
    $person = Employee::factory()->create(['department_id' => $department->id, 'job_role_id' => $role->id]);
    $skill = Skill::factory()->create();
    $low = SkillLevel::factory()->create(['skill_id' => $skill->id, 'ordinal' => 1]);
    $high = SkillLevel::factory()->create(['skill_id' => $skill->id, 'ordinal' => 3]);
    $departmentTarget = SkillTarget::factory()->create(['employee_id' => null, 'department_id' => $department->id, 'skill_id' => $skill->id, 'skill_level_id' => $low->id]);
    $roleTarget = SkillTarget::factory()->create(['employee_id' => null, 'job_role_id' => $role->id, 'skill_id' => $skill->id, 'skill_level_id' => $high->id]);
    $resolver = app(TargetResolver::class);
    expect($resolver->resolve($person, $skill)->ordinal)->toBe(3);
    expect($resolver->resolve($person, $skill)->targetIds)->toBe([$roleTarget->id]);
    $departmentTarget->update(['skill_level_id' => $high->id]);
    $tie = $resolver->resolve($person, $skill);
    expect($tie->source)->toBe('Department and job role targets (equal)');
    expect($tie->targetIds)->toBe([$departmentTarget->id, $roleTarget->id]);
    expect($resolver->resolve($person, $skill)->toArray())->toBe($tie->toArray());
});

it('distinguishes an assessed zero target from a missing target', function (): void {
    $person = Employee::factory()->create();
    $skill = Skill::factory()->create();
    $zero = SkillLevel::factory()->create(['skill_id' => $skill->id, 'ordinal' => 0]);
    $resolver = app(TargetResolver::class);
    expect($resolver->resolve($person, $skill)->ordinal)->toBeNull();
    SkillTarget::factory()->create(['employee_id' => $person->id, 'skill_id' => $skill->id, 'skill_level_id' => $zero->id, 'reason' => 'Illustrative zero target']);
    expect($resolver->resolve($person, $skill)->ordinal)->toBe(0);
});

it('does not let an unapproved or out-of-scope actor create an exception', function (): void {
    $account = User::factory()->withEmployee()->create();
    $level = SkillLevel::factory()->create();
    $this->actingAs($account)->withSession(['account_session_version' => $account->session_version]);
    $this->post('/skills/'.$level->skill_id.'/targets', ['scope' => 'employee', 'subject_id' => $account->employee_id, 'skill_level_id' => $level->id, 'reason' => 'Self approved', 'is_active' => true])->assertForbidden();
    $limitedHr = User::factory()->withEmployee()->create(['capabilities' => [Capability::ManageFrameworks->value]]);
    $this->actingAs($limitedHr)->withSession(['account_session_version' => $limitedHr->session_version]);
    $this->post('/skills/'.$level->skill_id.'/targets', ['scope' => 'employee', 'subject_id' => $account->employee_id, 'skill_level_id' => $level->id, 'reason' => 'Out of scope', 'is_active' => true])->assertForbidden();
    expect(SkillTarget::query()->count())->toBe(0);
});

it('refuses cross-skill and retired levels as current targets', function (): void {
    $hr = User::factory()->withEmployee()->create(['capabilities' => [Capability::ManageFrameworks->value]]);
    $skill = Skill::factory()->create();
    $other = SkillLevel::factory()->create();
    $retired = SkillLevel::factory()->create(['skill_id' => $skill->id, 'is_active' => false]);
    $department = Department::factory()->create();
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    foreach ([$other, $retired] as $level) {
        $this->post('/skills/'.$skill->id.'/targets', ['scope' => 'department', 'subject_id' => $department->id, 'skill_level_id' => $level->id, 'is_active' => true])->assertSessionHasErrors('skill_level_id');
    }
    expect(SkillTarget::query()->count())->toBe(0);
});

it('reconciles a repeated target save and requires an employee-visible exception reason', function (): void {
    $hr = User::factory()->withEmployee()->create(['capabilities' => [Capability::ManageFrameworks->value]]);
    $level = SkillLevel::factory()->create();
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $data = ['scope' => 'employee', 'subject_id' => $hr->employee_id, 'skill_level_id' => $level->id, 'is_active' => true];
    $this->post('/skills/'.$level->skill_id.'/targets', $data)->assertSessionHasErrors('reason');
    $data['reason'] = 'Illustrative training exception';
    $this->post('/skills/'.$level->skill_id.'/targets', $data)->assertRedirect()->assertSessionHasNoErrors();
    $this->post('/skills/'.$level->skill_id.'/targets', $data)->assertRedirect()->assertSessionHasNoErrors();
    expect(SkillTarget::query()->count())->toBe(1);
});

it('keeps inapplicable skills excluded unless HR explicitly includes an individual', function (): void {
    $person = Employee::factory()->create();
    $skill = Skill::factory()->create();
    $skill->departments()->attach(Department::factory()->create());
    $resolver = app(TargetResolver::class);
    expect($resolver->resolve($person, $skill)->applicable)->toBeFalse();
    $level = SkillLevel::factory()->create(['skill_id' => $skill->id]);
    SkillTarget::factory()->create(['employee_id' => $person->id, 'skill_id' => $skill->id, 'skill_level_id' => $level->id, 'reason' => 'Illustrative individual applicability']);
    expect($resolver->resolve($person, $skill)->applicable)->toBeTrue();
    $skill->update(['is_active' => false]);
    expect($resolver->resolve($person, $skill)->applicable)->toBeFalse();
});

it('reports an invalidated individual level without silently falling back to group policy', function (): void {
    $person = Employee::factory()->create();
    $target = SkillTarget::factory()->create(['employee_id' => $person->id, 'reason' => 'Illustrative exception']);
    $target->level->update(['is_active' => false]);
    $resolved = app(TargetResolver::class)->resolve($person, $target->skill);
    expect($resolved->ordinal)->toBeNull();
    expect($resolved->source)->toBe('Target needs HR attention: rating definition is retired or invalid');
});

it('rejects direct target-page access to a person outside the current reporting scope', function (): void {
    $account = User::factory()->withEmployee()->create();
    $outside = Employee::factory()->create();
    $this->actingAs($account)->withSession(['account_session_version' => $account->session_version]);
    $this->get('/employees/'.$outside->id.'/targets')->assertForbidden();
});

it('retires an exception and deterministically returns to the group target', function (): void {
    $hr = User::factory()->withEmployee()->create(['capabilities' => [Capability::ManageFrameworks->value]]);
    $department = Department::factory()->create();
    $hr->employee->forceFill(['department_id' => $department->id])->save();
    $skill = Skill::factory()->create();
    $high = SkillLevel::factory()->create(['skill_id' => $skill->id, 'ordinal' => 2]);
    $low = SkillLevel::factory()->create(['skill_id' => $skill->id, 'ordinal' => 0]);
    SkillTarget::factory()->create(['employee_id' => null, 'department_id' => $department->id, 'skill_id' => $skill->id, 'skill_level_id' => $high->id]);
    $exception = SkillTarget::factory()->create(['employee_id' => $hr->employee_id, 'skill_id' => $skill->id, 'skill_level_id' => $low->id, 'reason' => 'Temporary illustrative exception']);
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->post('/skills/'.$skill->id.'/targets', ['scope' => 'employee', 'subject_id' => $hr->employee_id, 'skill_level_id' => $low->id, 'reason' => $exception->reason, 'is_active' => false])->assertRedirect()->assertSessionHasNoErrors();
    expect($exception->refresh()->is_active)->toBeFalse();
    expect(app(TargetResolver::class)->resolve($hr->employee->fresh(), $skill)->ordinal)->toBe(2);
    expect(SkillTarget::query()->count())->toBe(2);
});
