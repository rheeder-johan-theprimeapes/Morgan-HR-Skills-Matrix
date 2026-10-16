<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SkillGroup;
use Illuminate\Database\Seeder;

class SkillGroupSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Illustrative fixtures are local/test only.');
        }
        SkillGroup::factory()->create();
    }
}
