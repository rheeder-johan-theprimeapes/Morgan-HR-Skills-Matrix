<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\SaveFramework;
use App\Capability;
use App\Models\ScoringFramework;
use App\Models\User;
use App\Services\FrameworkDefinition;
use Illuminate\Database\Seeder;

class ScoringFrameworkSeeder extends Seeder
{
    public function run(): void
    {
        if (config('demo.enabled') !== true || (! app()->environment('testing') && ! SkillsPreviewSeeder::isSafePreview())) {
            throw new \LogicException('Illustrative frameworks require an isolated fictional preview/test database.');
        }
        if (ScoringFramework::query()->where('name', 'Illustrative draft — HR approval required')->exists()) {
            return;
        }
        $hr = User::query()->get()->first(fn (User $user): bool => $user->hasCapability(Capability::ManageFrameworks));
        if ($hr === null) {
            return;
        }
        $editor = app(FrameworkDefinition::class)->editor(null);
        $input = ['components' => array_map(fn (array $group): array => ['group_id' => $group['group_id'], 'weight' => $group['weight'], 'skills' => array_map(fn (array $skill): array => ['skill_id' => $skill['skill_id'], 'included' => $skill['included'], 'required' => $skill['required'], 'requires_evidence' => $skill['requires_evidence'], 'levels' => array_map(fn (array $level): array => ['level_id' => $level['level_id'], 'points' => $level['points']], $skill['levels'])], $group['skills'])], $editor['components']), 'bands' => $editor['bands']];
        app(SaveFramework::class)->handle($hr, null, ['name' => 'Illustrative draft — HR approval required', 'illustrative' => true, 'expected_revision' => 0] + $input);
    }
}
