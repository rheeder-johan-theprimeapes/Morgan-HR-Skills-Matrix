<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\SaveOperationalRecord;
use App\Capability;
use App\Models\Employee;
use App\Models\OperationalRecord;
use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

class OperationalRecordSeeder extends Seeder
{
    public function run(): void
    {
        if (config('demo.enabled') !== true || (! app()->environment('testing') && ! SkillsPreviewSeeder::isSafePreview())) {
            throw new LogicException('Fictional context requires an isolated preview/test database.');
        }
        $actor = User::query()->where('login_id', 'DEMO-HR')->first();
        $person = Employee::query()->where('employee_number', 'DEMO-EMP')->first();
        if ($actor === null || $person === null || ! $actor->hasCapability(Capability::SensitiveContext) || OperationalRecord::query()->where('employee_id', $person->id)->exists()) {
            return;
        }
        app(SaveOperationalRecord::class)->handle($actor, $person, ['metric' => 'absence_hours', 'unit' => 'hours', 'value' => '3.50', 'period_start' => '2026-01-01', 'period_end' => '2026-01-31', 'source' => 'Fictional demonstration register', 'notes' => 'Entirely synthetic observation; no real employee context', 'expected_sequence' => 0]);
    }
}
