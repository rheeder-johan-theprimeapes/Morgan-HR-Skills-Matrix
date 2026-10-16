<?php

declare(strict_types=1);

use App\Actions\AddSkillLevel;
use App\Actions\SaveSkill;
use App\Actions\SaveSkillGroup;
use App\Actions\SaveSkillTarget;
use App\Capability;
use App\Models\Department;
use App\Models\SkillLevel;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;

uses(DatabaseTruncation::class);

it('rejects a capability revoked beyond the callers older transaction snapshot', function (string $operation): void {
    $actor = User::factory()->withEmployee()->create(['capabilities' => [Capability::ManageFrameworks->value]]);
    $level = SkillLevel::factory()->create();
    $skill = $level->skill;
    $department = Department::factory()->create();
    $settings = config('database.connections.mysql');
    expect(app()->environment())->toBe('testing');
    expect($settings['database'])->toBe('morgan_hr_skills_test');
    $other = DB::build($settings);
    expect($other->selectOne('SELECT DATABASE() AS name')->name)->toBe('morgan_hr_skills_test');
    DB::transaction(function () use ($actor, $skill, $level, $department, $other, $operation): void {
        expect(User::query()->findOrFail($actor->id)->hasCapability(Capability::ManageFrameworks))->toBeTrue();
        $other->table('users')->where('id', $actor->id)->update(['capabilities' => '[]']);
        $write = fn () => match ($operation) {
            'group' => app(SaveSkillGroup::class)->handle($actor, null, ['code' => 'CAP-CREATE', 'name' => 'Denied group', 'display_order' => 0, 'is_active' => true]),
            'skill' => app(SaveSkill::class)->handle($actor, null, ['code' => 'CAP-CREATE', 'name' => 'Denied skill', 'skill_group_id' => $skill->skill_group_id, 'is_active' => true, 'allows_na' => false, 'requires_evidence' => false]),
            'level' => app(AddSkillLevel::class)->handle($actor, $skill, ['ordinal' => 2, 'label' => 'Denied level', 'guidance' => 'Denied']),
            'target' => app(SaveSkillTarget::class)->handle($actor, $skill, ['scope' => 'department', 'subject_id' => $department->id, 'skill_level_id' => $level->id, 'is_active' => true]),
        };
        expect($write)->toThrow(AuthorizationException::class);
    });
    $this->assertDatabaseMissing('skill_groups', ['code' => 'CAP-CREATE']);
    $this->assertDatabaseMissing('skills', ['code' => 'CAP-CREATE']);
    $this->assertDatabaseCount('skill_levels', 1);
    $this->assertDatabaseCount('skill_targets', 0);
    $this->assertDatabaseCount('audit_events', 0);
})->with(['group', 'skill', 'level', 'target']);
