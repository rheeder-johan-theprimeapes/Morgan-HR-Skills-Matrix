<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SkillGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SkillGroup> */
class SkillGroupFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['code' => 'GROUP-'.fake()->unique()->numerify('######'), 'name' => 'Illustrative '.fake()->word().' '.fake()->word(), 'display_order' => 0, 'is_active' => true];
    }
}
