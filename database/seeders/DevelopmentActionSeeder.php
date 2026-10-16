<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\SaveDevelopmentAction;
use App\Models\DevelopmentAction;
use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

class DevelopmentActionSeeder extends Seeder
{
    public function run(): void
    {
        if (config('demo.enabled') !== true || (! app()->environment('testing') && ! SkillsPreviewSeeder::isSafePreview())) {
            throw new LogicException('Illustrative development requires an isolated preview/test database.');
        }
        $manager = User::query()->where('login_id', 'DEMO-MGR')->first();
        $employee = User::query()->where('login_id', 'DEMO-EMP')->first();
        $person = $employee->employee ?? null;
        if ($manager === null || $employee === null || $person === null || DevelopmentAction::query()->where('employee_id', $person->id)->exists()) {
            return;
        }
        app(SaveDevelopmentAction::class)->handle($manager, $person, null, ['mode' => 'plan', 'expected_revision' => 0, 'title' => 'Illustrative supported practice plan', 'description' => 'Fictional coaching for the agreed workstation procedure', 'starts_on' => today()->toDateString(), 'due_on' => today()->addMonth()->toDateString(), 'origin_kind' => 'manual']);
    }
}
