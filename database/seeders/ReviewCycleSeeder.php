<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\SaveReviewCycle;
use App\Capability;
use App\Models\ReviewCycle;
use App\Models\ScoringFramework;
use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

class ReviewCycleSeeder extends Seeder
{
    public function run(): void
    {
        if (config('demo.enabled') !== true || (! app()->environment('testing') && ! SkillsPreviewSeeder::isSafePreview())) {
            throw new LogicException('Illustrative cycles require an isolated preview/test database.');
        }
        if (ReviewCycle::query()->where('code', 'DEMO-PDR-2026')->exists()) {
            return;
        }
        $actor = User::query()->where('login_id', 'DEMO-HR')->first();
        $framework = ScoringFramework::query()->where('illustrative', true)->whereNotNull('published_at')->orderBy('id')->first();
        if ($actor === null || ! $actor->hasCapability(Capability::AdministerReviews) || $framework === null) {
            return;
        }
        app(SaveReviewCycle::class)->handle($actor, null, ['code' => 'DEMO-PDR-2026', 'name' => 'Illustrative PDR cycle — not live policy', 'scoring_framework_id' => $framework->id, 'opens_on' => today()->subDay()->toDateString(), 'closes_on' => today()->addMonths(3)->toDateString(), 'requires_employee_input' => true]);
    }
}
