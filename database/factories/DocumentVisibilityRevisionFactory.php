<?php

declare(strict_types=1);

namespace Database\Factories;

use App\DocumentVisibility;
use App\Models\DocumentVisibilityRevision;
use App\Models\HistoricalDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentVisibilityRevision> */
class DocumentVisibilityRevisionFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['historical_document_id' => HistoricalDocument::factory(), 'revision' => 1, 'visibility' => DocumentVisibility::HrOnly, 'content_review_confirmed' => false, 'reason' => 'Fictional initial archive', 'actor_id' => User::factory()->withEmployee()];
    }
}
