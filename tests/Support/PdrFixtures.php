<?php

declare(strict_types=1);

use App\Actions\AllocatePdrReview;
use App\AssessmentKind;
use App\AssessmentState;
use App\Capability;
use App\Models\Assessment;
use App\Models\AssessmentRevision;
use App\Models\PdrReview;
use App\Models\ReviewCycle;
use App\Models\ScoringFramework;
use App\Models\Skill;
use App\Models\SkillLevel;
use App\Models\User;

/** @return array{User,User,User,ReviewCycle,PdrReview,Assessment,AssessmentRevision} */
function submissionFixture(): array
{
    $hr = User::factory()->withEmployee()->create(['capabilities' => [Capability::AdministerReviews->value, Capability::ViewOrganisation->value]]);
    $manager = User::factory()->withEmployee()->create();
    $employee = User::factory()->withEmployee()->create();
    $person = $employee->employee;
    if ($person === null) {
        throw new LogicException('The fixture requires a linked employee.');
    }
    $person->forceFill(['manager_id' => $manager->employee_id])->save();
    $skill = Skill::factory()->create();
    $low = SkillLevel::factory()->for($skill)->create(['ordinal' => 0]);
    $high = SkillLevel::factory()->for($skill)->create(['ordinal' => 1]);
    $definition = ['components' => [['group_id' => $skill->skill_group_id, 'name' => 'Illustrative skills', 'weight' => 1, 'skills' => [['skill_id' => $skill->id, 'name' => $skill->name, 'allows_na' => true, 'required' => true, 'requires_evidence' => true, 'department_ids' => [], 'job_role_ids' => [], 'levels' => [['level_id' => $low->id, 'ordinal' => 0, 'label' => 'Learning', 'points' => 0], ['level_id' => $high->id, 'ordinal' => 1, 'label' => 'Supported practice', 'points' => 10000]]]]]], 'bands' => [['label' => 'Illustrative band', 'from' => 0, 'to' => 10000]]];
    $framework = ScoringFramework::factory()->create(['definition' => $definition, 'published_at' => now()]);
    $cycle = ReviewCycle::factory()->create(['scoring_framework_id' => $framework->id, 'requires_employee_input' => true]);
    $review = app(AllocatePdrReview::class)->handle($hr, $cycle, ['employee_id' => $employee->employee_id, 'manager_user_id' => $manager->id, 'section_profile' => 'production', 'expected_version' => 0]);
    $draft = $review->currentDraft;
    if ($draft === null) {
        throw new LogicException('The fixture requires an allocated draft.');
    }
    $draft->update(['employee_section' => ['reflection' => 'Fictional reflection', 'achievements' => 'Submitted achievement examples', 'development_needs' => 'Submitted coaching request'], 'manager_section' => ['summary' => 'Internal manager summary', 'objectives' => [['description' => 'Practice', 'measure' => 'Two observed demonstrations', 'due_on' => '2027-01-15']]]]);
    $assessment = Assessment::factory()->create(['employee_id' => $employee->employee_id, 'skill_id' => $skill->id, 'kind' => AssessmentKind::Manager]);
    $revision = AssessmentRevision::factory()->create(['assessment_id' => $assessment->id, 'state' => AssessmentState::Rated, 'skill_level_id' => $high->id, 'actor_id' => $manager->id, 'evidence_notes' => 'Private observation evidence']);
    $assessment->update(['revision' => 1, 'current_revision_id' => $revision->id]);

    return [$hr, $manager, $employee, $cycle, $review, $assessment, $revision];
}

/** @return array{expected_version:int,expected_revision:int} */
function submissionTokens(PdrReview $review): array
{
    $review->refresh();

    $draft = $review->currentDraft;
    if ($draft === null) {
        throw new LogicException('The fixture requires a current draft.');
    }

    return ['expected_version' => $review->version, 'expected_revision' => $draft->revision];
}
