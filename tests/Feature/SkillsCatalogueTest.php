<?php

declare(strict_types=1);

use App\Capability;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobRole;
use App\Models\Skill;
use App\Models\SkillGroup;
use App\Models\SkillLevel;
use App\Models\User;
use App\Services\SkillApplicability;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('requires an explicit framework capability for catalogue administration', function (): void {
    $hr = User::factory()->withEmployee()->create(['capabilities' => [Capability::ManageFrameworks->value]]);
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->get('/skills')->assertOk();
    $this->post('/skill-groups', ['code' => 'PRODUCTION', 'name' => 'Production skills', 'display_order' => 1, 'is_active' => true])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('skill_groups', ['code' => 'PRODUCTION', 'name' => 'Production skills']);
});

it('creates an assigned skill with ordered append-only rating guidance', function (): void {
    $hr = User::factory()->withEmployee()->create(['capabilities' => [Capability::ManageFrameworks->value]]);
    $group = SkillGroup::factory()->create();
    $department = Department::factory()->create();
    $role = JobRole::factory()->create();
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->post('/skills', ['code' => 'DEMO-CNC', 'name' => 'Illustrative machining', 'description' => 'Demonstration only', 'skill_group_id' => $group->id, 'allows_na' => true, 'requires_evidence' => true, 'is_active' => true, 'department_ids' => [$department->id], 'job_role_ids' => [$role->id]])->assertRedirect()->assertSessionHasNoErrors();
    $skill = Skill::query()->where('code', 'DEMO-CNC')->firstOrFail();
    expect($skill->departments->modelKeys())->toBe([$department->id]);
    expect($skill->jobRoles->modelKeys())->toBe([$role->id]);
    foreach ([2 => 'Independent', 0 => 'No demonstrated competence'] as $ordinal => $label) {
        $this->post('/skills/'.$skill->id.'/levels', ['ordinal' => $ordinal, 'label' => $label, 'guidance' => 'Illustrative evidence guidance'])->assertRedirect()->assertSessionHasNoErrors();
    }
    expect($skill->levels()->pluck('ordinal')->all())->toBe([0, 2]);
    $this->get('/skills/'.$skill->id)->assertOk()->assertSee('No demonstrated competence')->assertSee('Independent');
    $form = $this->get('/skills/create')->assertOk();
    $document = new DOMDocument;
    $document->loadHTML($form->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    expect($document->getElementById('department_ids')->hasAttribute('multiple'))->toBeTrue();
    expect($document->getElementById('job_role_ids')->hasAttribute('multiple'))->toBeTrue();
    $this->get('/skills/'.$skill->id.'/edit')->assertOk();
    $this->assertDatabaseHas('audit_events', ['event' => 'catalogue.skill.saved', 'subject_id' => $skill->id]);
});

it('keeps directory and account authority separate from catalogue authority', function (): void {
    $administrator = User::factory()->directoryAdministrator()->accountAdministrator()->create();
    $this->actingAs($administrator)->withSession(['account_session_version' => $administrator->session_version]);
    $this->get('/skills')->assertForbidden();
    $this->post('/skill-groups', ['code' => 'FORGED', 'name' => 'Forged', 'is_active' => true])->assertForbidden();
    $this->assertDatabaseMissing('skill_groups', ['code' => 'FORGED']);
});

it('rejects duplicate and malformed rating ordinals without partial writes', function (): void {
    $hr = User::factory()->withEmployee()->create(['capabilities' => [Capability::ManageFrameworks->value]]);
    $level = SkillLevel::factory()->create(['ordinal' => 0]);
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    foreach ([0, -1, 1.5, 101] as $ordinal) {
        $this->post('/skills/'.$level->skill_id.'/levels', ['ordinal' => $ordinal, 'label' => 'Rejected', 'guidance' => 'Example'])->assertSessionHasErrors('ordinal');
    }
    expect(SkillLevel::query()->count())->toBe(1);
});

it('preserves definitions when retiring skills and prevents hard deletion', function (): void {
    $hr = User::factory()->withEmployee()->create(['capabilities' => [Capability::ManageFrameworks->value]]);
    $level = SkillLevel::factory()->create(['label' => 'Original meaning']);
    $skill = $level->skill;
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->put('/skills/'.$skill->id, ['name' => $skill->name, 'description' => $skill->description, 'skill_group_id' => $skill->skill_group_id, 'allows_na' => $skill->allows_na, 'requires_evidence' => $skill->requires_evidence, 'is_active' => false])->assertRedirect()->assertSessionHasNoErrors();
    $this->get('/skills/'.$skill->id)->assertOk()->assertSee('Original meaning')->assertSee('Retired');
    $this->delete('/skills/'.$skill->id)->assertStatus(405);
    $this->assertModelExists($level);
    expect($skill->refresh()->is_active)->toBeFalse();
});

it('retires rating definitions without allowing their historical meaning to change', function (): void {
    $hr = User::factory()->withEmployee()->create(['capabilities' => [Capability::ManageFrameworks->value]]);
    $level = SkillLevel::factory()->create(['ordinal' => 0, 'label' => 'Original zero']);
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->put('/skill-levels/'.$level->id, ['is_active' => false, 'label' => 'Changed'])->assertSessionHasErrors('label');
    $this->put('/skill-levels/'.$level->id, ['is_active' => false])->assertRedirect()->assertSessionHasNoErrors();
    expect($level->refresh()->label)->toBe('Original zero');
    expect($level->is_active)->toBeFalse();
});

it('rejects an active skill in a retired group and prevents retiring a group with active skills', function (): void {
    $hr = User::factory()->withEmployee()->create(['capabilities' => [Capability::ManageFrameworks->value]]);
    $skill = Skill::factory()->create();
    $retired = SkillGroup::factory()->create(['is_active' => false]);
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->post('/skills', ['code' => 'BAD-GROUP', 'name' => 'Invalid', 'skill_group_id' => $retired->id, 'is_active' => true, 'allows_na' => false, 'requires_evidence' => false])->assertSessionHasErrors('skill_group_id');
    $this->put('/skill-groups/'.$skill->skill_group_id, ['name' => 'Attempted retirement', 'display_order' => 0, 'is_active' => false])->assertSessionHasErrors('is_active');
    expect($skill->group->refresh()->is_active)->toBeTrue();
});

it('resolves global or matching role and department applicability without granting access', function (): void {
    $employee = Employee::factory()->create();
    $global = Skill::factory()->create();
    $restricted = Skill::factory()->create();
    $department = Department::factory()->create();
    $role = JobRole::factory()->create();
    $restricted->departments()->attach($department);
    $restricted->jobRoles()->attach($role);
    $applicability = app(SkillApplicability::class);
    expect($applicability->includes($employee, $global))->toBeTrue();
    expect($applicability->includes($employee, $restricted))->toBeFalse();
    $employee->forceFill(['job_role_id' => $role->id])->save();
    expect($applicability->includes($employee, $restricted))->toBeTrue();
    $restricted->forceFill(['is_active' => false])->save();
    expect($applicability->includes($employee, $restricted))->toBeFalse();
});

it('does not allow stable codes to be renamed by ordinary edits', function (): void {
    $hr = User::factory()->withEmployee()->create(['capabilities' => [Capability::ManageFrameworks->value]]);
    $skill = Skill::factory()->create();
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->put('/skills/'.$skill->id, ['code' => 'RENAMED', 'name' => $skill->name, 'skill_group_id' => $skill->skill_group_id, 'is_active' => true, 'allows_na' => false, 'requires_evidence' => false])->assertSessionHasErrors('code');
    expect($skill->refresh()->code)->not->toBe('RENAMED');
});

it('retains inactive applicability assignments without broadening a skill to everyone', function (): void {
    $hr = User::factory()->withEmployee()->create(['capabilities' => [Capability::ManageFrameworks->value]]);
    $skill = Skill::factory()->create();
    $department = Department::factory()->create();
    $skill->departments()->attach($department);
    $department->forceFill(['is_active' => false])->save();
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->get('/skills/'.$skill->id.'/edit')->assertOk()->assertSee($department->name);
    $this->put('/skills/'.$skill->id, ['name' => 'Corrected skill', 'skill_group_id' => $skill->skill_group_id, 'allows_na' => false, 'requires_evidence' => false, 'is_active' => true, 'department_ids' => [$department->id]])->assertRedirect()->assertSessionHasNoErrors();
    expect($skill->fresh()->departments->modelKeys())->toBe([$department->id]);
    expect(app(SkillApplicability::class)->includes(Employee::factory()->create(), $skill->fresh()))->toBeFalse();
});
