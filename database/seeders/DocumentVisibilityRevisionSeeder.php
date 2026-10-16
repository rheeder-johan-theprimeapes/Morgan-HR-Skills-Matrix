<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DocumentVisibilityRevisionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(HistoricalDocumentSeeder::class);
    }
}
