<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Skill;
use Illuminate\Database\Seeder;

class SkillSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Illustrative fixtures are local/test only.');
        }
        Skill::factory()->create();
    }
}
