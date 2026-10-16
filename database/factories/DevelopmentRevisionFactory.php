<?php

declare(strict_types=1);

namespace Database\Factories;

use App\DevelopmentState;
use App\Models\DevelopmentAction;
use App\Models\DevelopmentRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DevelopmentRevision> */
class DevelopmentRevisionFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['development_action_id' => DevelopmentAction::factory(), 'revision' => 1, 'change_type' => 'plan', 'title' => 'Illustrative supported practice', 'description' => 'Fictional coaching plan', 'starts_on' => today()->toDateString(), 'due_on' => today()->addMonth()->toDateString(), 'state' => DevelopmentState::Planned, 'actor_id' => User::factory()->withEmployee()];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (DevelopmentRevision $revision): void {
            $head = $revision->action;
            if ($head !== null && $head->current_revision_id === null) {
                $head->update(['revision' => $revision->revision, 'current_revision_id' => $revision->id]);
            }
        });
    }
}
