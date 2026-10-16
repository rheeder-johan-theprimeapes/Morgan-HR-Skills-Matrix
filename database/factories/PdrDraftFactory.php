<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PdrDraft;
use App\Models\PdrReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PdrDraft> */
class PdrDraftFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['pdr_review_id' => PdrReview::factory(), 'draft_number' => 1, 'revision' => 1, 'employee_section' => [], 'manager_section' => [], 'pay_proposal' => null];
    }
}
