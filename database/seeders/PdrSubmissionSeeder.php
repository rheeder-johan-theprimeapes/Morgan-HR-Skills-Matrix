<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\SubmitPdrReview;
use App\Models\PdrReview;
use App\Models\ReviewCycle;
use App\Models\User;
use App\PdrState;
use App\Services\EmployeeScore;
use Illuminate\Database\Seeder;
use LogicException;

class PdrSubmissionSeeder extends Seeder
{
    public function run(): void
    {
        if (config('demo.enabled') !== true || (! app()->environment('testing') && ! SkillsPreviewSeeder::isSafePreview())) {
            throw new LogicException('Illustrative submissions require an isolated preview/test database.');
        }
        $this->call(PdrDraftSeeder::class);
        $cycle = ReviewCycle::query()->where('code', 'DEMO-PDR-2026')->first();
        $manager = User::query()->where('login_id', 'DEMO-MGR')->first();
        $employee = User::query()->where('login_id', 'DEMO-EMP')->first();
        if ($cycle === null || $manager === null || $employee === null) {
            return;
        }
        $review = PdrReview::query()->where('review_cycle_id', $cycle->id)->where('employee_id', $employee->employee_id)->first();
        $person = $review->employee ?? null;
        $draft = $review->currentDraft ?? null;
        $framework = $cycle->framework;
        if ($review === null || $person === null || $draft === null || $framework === null || $review->state !== PdrState::Draft || ! app(EmployeeScore::class)->preview($manager, $person, $framework)['complete']) {
            return;
        }
        app(SubmitPdrReview::class)->handle($manager, $review, ['expected_version' => $review->version, 'expected_revision' => $draft->revision]);
    }
}
