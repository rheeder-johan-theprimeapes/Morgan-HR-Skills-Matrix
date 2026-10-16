<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AssessmentRevision;
use App\Models\EvidenceAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EvidenceAttachment> */
class EvidenceAttachmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['assessment_revision_id' => AssessmentRevision::factory(), 'uploaded_by' => User::factory()->withEmployee(), 'original_name' => 'illustrative.pdf', 'storage_path' => fake()->uuid(), 'mime_type' => 'application/pdf', 'byte_size' => 0, 'sha256' => hash('sha256', '')];
    }
}
