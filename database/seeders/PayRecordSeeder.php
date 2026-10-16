<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\SavePayRecord;
use App\Capability;
use App\Models\Employee;
use App\Models\PayRecord;
use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

class PayRecordSeeder extends Seeder
{
    public function run(): void
    {
        if (config('demo.enabled') !== true || (! app()->environment('testing') && ! SkillsPreviewSeeder::isSafePreview())) {
            throw new LogicException('Fictional pay requires an isolated preview/test database.');
        }
        $actor = User::query()->where('login_id', 'DEMO-HR')->first();
        $person = Employee::query()->where('employee_number', 'DEMO-EMP')->first();
        if ($actor === null || $person === null || ! $actor->hasCapability(Capability::ActualPay) || PayRecord::query()->where('employee_id', $person->id)->exists()) {
            return;
        }
        app(SavePayRecord::class)->handle($actor, $person, ['amount' => '50000.00', 'basis' => 'annual', 'currency' => 'GBP', 'effective_on' => '2026-01-01', 'source' => 'Fictional demonstration pay — not a real employee record', 'expected_sequence' => 0]);
    }
}
