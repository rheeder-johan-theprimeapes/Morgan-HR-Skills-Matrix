<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use LogicException;

class PdrReleaseSeeder extends Seeder
{
    public function run(): void
    {
        if (config('demo.enabled') !== true || (! app()->environment('testing') && ! SkillsPreviewSeeder::isSafePreview())) {
            throw new LogicException('Illustrative review fixtures require an isolated preview/test database.');
        }
        $this->call(PdrFeedbackSeeder::class);
    }
}
