<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\AddSkillLevel;
use App\Actions\SaveAssessment;
use App\Actions\SaveSkill;
use App\Actions\SaveSkillGroup;
use App\Actions\SaveSkillTarget;
use App\Capability;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

class SkillsPreviewSeeder extends Seeder
{
    public static function isSafePreview(): bool
    {
        $database = config('database.connections.sqlite.database');

        return app()->environment('local') && config('demo.enabled') === true && config('database.default') === 'sqlite' && is_string($database)
            && strtolower(str_replace('\\', '/', $database)) === strtolower(str_replace('\\', '/', storage_path('app/private/skills-preview.sqlite')));
    }

    public function run(): void
    {
        if (! self::isSafePreview()) {
            throw new LogicException('Fictional preview cannot run against this connection.');
        }
        if (Employee::query()->exists()) {
            throw new LogicException('Preview seeding requires an empty dedicated preview. Existing records are preserved.');
        }
        $production = Department::factory()->create(['code' => 'DEMO-PROD', 'name' => 'Production (illustrative)']);
        $office = Department::factory()->create(['code' => 'DEMO-OFFICE', 'name' => 'Office (illustrative)']);
        $operator = JobRole::factory()->create(['code' => 'DEMO-OP', 'name' => 'Operator (illustrative)']);
        $coordinator = JobRole::factory()->create(['code' => 'DEMO-COORD', 'name' => 'Coordinator (illustrative)']);
        $supervisor = JobRole::factory()->create(['code' => 'DEMO-SUP', 'name' => 'Supervisor (illustrative)']);
        $hr = $this->account('DEMO-HR', 'Alex Morgan · Fictional HR', $office->id, $coordinator->id);
        $hr->forceFill(['can_manage_directory' => true, 'can_administer_accounts' => true, 'capabilities' => [Capability::ViewOrganisation->value, Capability::ManageFrameworks->value, Capability::AdministerReviews->value]])->save();
        $manager = $this->account('DEMO-MGR', 'Robin Ellis · Fictional manager', $production->id, $supervisor->id);
        $employee = $this->account('DEMO-EMP', 'Taylor Reed · Fictional operator', $production->id, $operator->id, $manager->employee_id);
        $employeePerson = $employee->employee()->firstOrFail();
        $lead = $this->account('DEMO-LEAD', 'Sam Avery · Fictional team lead', $production->id, $supervisor->id, $manager->employee_id);
        $this->account('DEMO-OFF', 'Jamie Cole · Fictional coordinator', $office->id, $coordinator->id, $lead->employee_id);
        $this->account('DEMO-OTHER', 'Casey Lane · Fictional separate team', $office->id, $coordinator->id);
        Employee::factory()->count(26)->sequence(fn ($sequence): array => ['employee_number' => 'DEMO-'.str_pad((string) ($sequence->index + 1), 3, '0', STR_PAD_LEFT), 'name' => 'Fictional colleague '.str_pad((string) ($sequence->index + 1), 2, '0', STR_PAD_LEFT)])->create(['department_id' => $production->id, 'job_role_id' => $operator->id, 'manager_id' => $manager->employee_id]);
        $groups = [];
        foreach (['Foundations', 'Production', 'Office', 'Leadership'] as $order => $name) {
            $groups[] = app(SaveSkillGroup::class)->handle($hr, null, ['code' => 'DEMO-GROUP-'.$order, 'name' => $name.' · illustrative', 'display_order' => $order, 'is_active' => true]);
        }
        for ($index = 0; $index < 15; $index++) {
            $groupIndex = $index === 0 ? 0 : ($index < 6 ? 1 : ($index < 11 ? 2 : 3));
            $roleIds = match ($groupIndex) {
                1 => [$operator->id], 2 => [$coordinator->id], 3 => [$supervisor->id], default => []
            };
            $skill = app(SaveSkill::class)->handle($hr, null, ['code' => 'DEMO-SKILL-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT), 'name' => $index === 0 ? 'Safe working · illustrative' : $groups[$groupIndex]->name.' skill '.str_pad((string) $index, 2, '0', STR_PAD_LEFT), 'description' => 'Fictional technical demonstration; HR has not approved this live policy.', 'skill_group_id' => $groups[$groupIndex]->id, 'allows_na' => true, 'requires_evidence' => $index === 0, 'is_active' => true, 'department_ids' => [], 'job_role_ids' => $roleIds]);
            $levels = [];
            foreach (['No demonstrated evidence', 'Supported practice', 'Independent routine work', 'Advanced practice', 'Supports others'] as $ordinal => $label) {
                $levels[] = app(AddSkillLevel::class)->handle($hr, $skill, ['ordinal' => $ordinal, 'label' => 'Illustrative: '.$label, 'guidance' => 'Demonstration only. Use dated observed examples; HR confirms real rating definitions.']);
            }
            app(SaveSkillTarget::class)->handle($hr, $skill, ['scope' => 'job_role', 'subject_id' => ($roleIds[0] ?? $operator->id), 'skill_level_id' => $levels[3]->id, 'is_active' => true]);
            if ($index === 0) {
                app(SaveSkillTarget::class)->handle($hr, $skill, ['scope' => 'employee', 'subject_id' => $employee->employee_id, 'skill_level_id' => $levels[1]->id, 'is_active' => true, 'reason' => 'Illustrative onboarding target; not approved live policy.']);
            }
            if ($groupIndex < 2) {
                app(SaveAssessment::class)->handle($employee, $employeePerson, $skill, ['kind' => 'self', 'state' => $index === 1 ? 'not_applicable' : 'rated', 'skill_level_id' => $index === 1 ? null : $levels[$index === 0 ? 0 : 2]->id, 'assessed_on' => now()->toDateString(), 'comment' => $index === 1 ? 'Illustrative temporary duty exclusion' : 'Fictional self reflection', 'evidence_notes' => 'Illustrative observed example on '.now()->toDateString(), 'expected_revision' => 0]);
                app(SaveAssessment::class)->handle($manager, $employeePerson, $skill, ['kind' => 'manager', 'state' => 'rated', 'skill_level_id' => $levels[1]->id, 'assessed_on' => now()->toDateString(), 'comment' => 'Fictional confidential manager draft', 'evidence_notes' => 'Illustrative manager observation on '.now()->toDateString(), 'expected_revision' => 0]);
            }
        }
    }

    private function account(string $number, string $name, int $departmentId, int $roleId, ?int $managerId = null): User
    {
        $person = Employee::factory()->create(['employee_number' => $number, 'name' => $name, 'department_id' => $departmentId, 'job_role_id' => $roleId, 'manager_id' => $managerId]);

        return User::factory()->create(['employee_id' => $person->id, 'login_id' => $number, 'name' => $name, 'email' => null, 'password' => 'local-preview-passphrase']);
    }
}
