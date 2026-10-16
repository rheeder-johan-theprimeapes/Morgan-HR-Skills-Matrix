<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\AllocatePdrReview;
use App\Capability;
use App\Models\Employee;
use App\Models\PdrReview;
use App\Models\ReviewCycle;
use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

class PdrReviewSeeder extends Seeder
{
    public function run(): void
    {
        if (config('demo.enabled') !== true || (! app()->environment('testing') && ! SkillsPreviewSeeder::isSafePreview())) {
            throw new LogicException('Illustrative allocations require an isolated preview/test database.');
        }
        $this->call(ReviewCycleSeeder::class);
        $cycle = ReviewCycle::query()->where('code', 'DEMO-PDR-2026')->first();
        $actor = User::query()->where('login_id', 'DEMO-HR')->first();
        $manager = User::query()->where('login_id', 'DEMO-MGR')->first();
        $person = Employee::query()->where('employee_number', 'DEMO-EMP')->first();
        if ($cycle === null || $actor === null || $manager === null || $person === null || ! $actor->hasCapability(Capability::AdministerReviews) || PdrReview::query()->where('review_cycle_id', $cycle->id)->where('employee_id', $person->id)->exists()) {
            return;
        }
        app(AllocatePdrReview::class)->handle($actor, $cycle, ['employee_id' => $person->id, 'manager_user_id' => $manager->id, 'section_profile' => 'production', 'expected_version' => 0]);
    }
}
