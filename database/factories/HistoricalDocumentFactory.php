<?php

declare(strict_types=1);

namespace Database\Factories;

use App\DocumentVisibility;
use App\Models\Employee;
use App\Models\HistoricalDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<HistoricalDocument> */
class HistoricalDocumentFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['employee_id' => Employee::factory(), 'title' => 'Illustrative historical archive', 'document_date' => '2025-04-01', 'source' => 'Fictional archive reference', 'uploaded_by' => User::factory()->withEmployee(), 'original_name' => 'fictional-history.pdf', 'storage_path' => 'historical/'.Str::uuid().'.pdf', 'mime_type' => 'application/pdf', 'byte_size' => strlen(self::content()), 'sha256' => hash('sha256', self::content()), 'visibility' => DocumentVisibility::HrOnly, 'revision' => 1];
    }

    public static function content(): string
    {
        return "%PDF-1.4\nFictional historical test document";
    }
}
