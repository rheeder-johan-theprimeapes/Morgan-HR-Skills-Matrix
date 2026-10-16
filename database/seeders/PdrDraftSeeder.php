<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\SavePdrDraft;
use App\Models\PdrReview;
use App\Models\ReviewCycle;
use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

class PdrDraftSeeder extends Seeder
{
    public function run(): void
    {
        if (config('demo.enabled') !== true || (! app()->environment('testing') && ! SkillsPreviewSeeder::isSafePreview())) {
            throw new LogicException('Illustrative drafts require an isolated preview/test database.');
        }
        $this->call(PdrReviewSeeder::class);
        $cycle = ReviewCycle::query()->where('code', 'DEMO-PDR-2026')->first();
        $employee = User::query()->where('login_id', 'DEMO-EMP')->first();
        $manager = User::query()->where('login_id', 'DEMO-MGR')->first();
        if ($cycle === null || $employee === null || $manager === null) {
            return;
        }
        $review = PdrReview::query()->where('review_cycle_id', $cycle->id)->where('employee_id', $employee->employee_id)->first();
        $draft = $review->currentDraft ?? null;
        if ($review === null || $draft === null || $draft->revision !== 1) {
            return;
        }
        app(SavePdrDraft::class)->handle($employee, $review, ['section' => 'employee', 'expected_revision' => 1, 'employee_section' => ['reflection' => 'Fictional learning reflection', 'achievements' => 'Illustrative observed example', 'development_needs' => 'Fictional supported practice plan']]);
        app(SavePdrDraft::class)->handle($manager, $review, ['section' => 'manager', 'expected_revision' => 2, 'manager_section' => ['summary' => 'Fictional confidential manager summary', 'role_notes' => 'Illustrative process observations', 'objectives' => [['description' => 'Complete fictional supported practice', 'measure' => 'Three illustrative observed examples', 'due_on' => today()->addMonths(2)->toDateString()]]]]);
    }
}
