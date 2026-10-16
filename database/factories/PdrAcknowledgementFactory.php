<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PdrAcknowledgement;
use App\Models\PdrRelease;
use App\Models\PdrReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PdrAcknowledgement> */
class PdrAcknowledgementFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['pdr_release_id' => PdrRelease::factory(), 'employee_id' => function (array $attributes): int {
            $release = PdrRelease::query()->whereKey($attributes['pdr_release_id'])->firstOrFail();

            return PdrReview::query()->whereKey($release->pdr_review_id)->firstOrFail()->employee_id;
        }, 'actor_id' => fn (array $attributes): int => User::factory()->create(['employee_id' => $attributes['employee_id']])->id, 'choice' => 'receipt', 'comment' => 'Illustrative receipt, not agreement'];
    }
}
