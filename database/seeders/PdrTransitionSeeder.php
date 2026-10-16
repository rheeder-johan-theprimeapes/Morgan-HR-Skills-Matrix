<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PdrTransitionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PdrSubmissionSeeder::class);
    }
}
