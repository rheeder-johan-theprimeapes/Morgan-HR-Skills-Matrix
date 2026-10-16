<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\SavePayBenchmark;
use App\Capability;
use App\Models\JobRole;
use App\Models\PayBenchmark;
use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

class PayBenchmarkSeeder extends Seeder
{
    public function run(): void
    {
        if (config('demo.enabled') !== true || (! app()->environment('testing') && ! SkillsPreviewSeeder::isSafePreview())) {
            throw new LogicException('Fictional benchmarks require an isolated preview/test database.');
        }
        $actor = User::query()->where('login_id', 'DEMO-HR')->first();
        $role = JobRole::query()->where('code', 'DEMO-OP')->first();
        if ($actor === null || $role === null || ! $actor->hasCapability(Capability::Benchmarks) || PayBenchmark::query()->where('title', 'Illustrative sourced comparator')->exists()) {
            return;
        }
        app(SavePayBenchmark::class)->handle($actor, ['amount' => '25.00', 'basis' => 'hourly', 'currency' => 'GBP', 'weekly_hours' => '37.50', 'weeks_per_year' => '52.00', 'job_role_id' => $role->id, 'title' => 'Illustrative sourced comparator', 'company_context' => 'Fictional engineering company', 'duties' => 'Illustrative production duties', 'limitations' => 'Synthetic demonstration; not market evidence or advice', 'source' => 'Fictional HR-curated survey', 'source_date' => '2026-01-01', 'valid_from' => '2026-01-01', 'valid_to' => '2026-12-31']);
    }
}
