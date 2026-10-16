<?php

declare(strict_types=1);

use App\Capability;
use App\Models\AssessmentRevision;
use App\Models\EvidenceAttachment;
use App\Models\OperationalRecord;
use App\Models\PayRecord;
use App\Models\PdrSubmission;
use App\Models\PdrTransition;
use App\Models\User;
use App\Services\EvidenceAvailability;
use App\Services\ScoreCalculator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

require_once __DIR__.'/../Support/PdrFixtures.php';

function reviewSignIn(User $actor): void
{
    test()->actingAs($actor)->withSession(['account_session_version' => $actor->session_version]);
}

it('submits once with a frozen reproducible revision and locks the exact draft', function (): void {
    [$hr,$manager,$employee,$cycle,$review,$assessment,$revision] = submissionFixture();
    reviewSignIn($manager);
    $tokens = submissionTokens($review);
    $this->post('/reviews/'.$review->id.'/submit', $tokens)->assertSessionHasNoErrors()->assertRedirect();
    $submission = PdrSubmission::query()->sole();
    expect($review->refresh()->state->value)->toBe('submitted')->and($review->currentDraft->locked_at)->not->toBeNull();
    expect($submission->snapshot['score']['score'])->toBe(100)->and($submission->snapshot['assessments'][0]['id'])->toBe($revision->id);
    $this->postJson('/reviews/'.$review->id.'/submit', $tokens)->assertStatus(409);
    expect(PdrSubmission::query()->count())->toBe(1);
    $this->postJson('/reviews/'.$review->id.'/draft', ['section' => 'manager', 'expected_revision' => 1, 'manager_section' => ['summary' => 'Rewrite']])->assertStatus(409);
    expect(fn () => $submission->update(['snapshot' => []]))->toThrow(LogicException::class);
});

it('rejects incomplete employee manager objective and required evidence without partial submissions', function (string $missing): void {
    [$hr,$manager,$employee,$cycle,$review,$assessment,$revision] = submissionFixture();
    $draft = $review->currentDraft;
    if ($missing === 'employee') {
        $draft->update(['employee_section' => []]);
    }
    if ($missing === 'manager') {
        $draft->update(['manager_section' => ['summary' => '', 'objectives' => []]]);
    }
    if ($missing === 'objective') {
        $draft->update(['manager_section' => ['summary' => 'Summary', 'objectives' => [['description' => 'Incomplete']]]]);
    }
    if ($missing === 'evidence') {
        AssessmentRevision::query()->whereKey($revision->id)->update(['evidence_notes' => null]);
    }
    reviewSignIn($manager);
    $this->postJson('/reviews/'.$review->id.'/submit', submissionTokens($review))->assertUnprocessable();
    expect(PdrSubmission::query()->count())->toBe(0)->and($review->currentDraft->fresh()->locked_at)->toBeNull();
})->with(['employee', 'manager', 'objective', 'evidence']);

it('requires current assigned manager cycle window and both optimistic tokens', function (): void {
    [$hr,$manager,$employee,$cycle,$review] = submissionFixture();
    foreach ([$hr, $employee] as $actor) {
        reviewSignIn($actor);
        $this->postJson('/reviews/'.$review->id.'/submit', submissionTokens($review))->assertForbidden();
    }
    reviewSignIn($manager);
    $tokens = submissionTokens($review);
    $tokens['expected_revision'] = 999;
    $this->postJson('/reviews/'.$review->id.'/submit', $tokens)->assertUnprocessable();
    $cycle->update(['closes_on' => today()->subDay()]);
    $this->postJson('/reviews/'.$review->id.'/submit', submissionTokens($review))->assertStatus(409);
    $cycle->update(['closes_on' => today()->addMonth()]);
    $employee->employee->forceFill(['manager_id' => null])->save();
    $this->postJson('/reviews/'.$review->id.'/submit', submissionTokens($review))->assertForbidden();
    expect(PdrSubmission::query()->count())->toBe(0);
});

it('preserves immutable private file copies and source facts after original changes', function (): void {
    Storage::fake('evidence');
    [$hr,$manager,$employee,$cycle,$review,$assessment,$revision] = submissionFixture();
    Storage::disk('evidence')->put('original/private.txt', 'Fictional immutable observation');
    EvidenceAttachment::factory()->create(['assessment_revision_id' => $revision->id, 'storage_path' => 'original/private.txt', 'original_name' => 'observation.txt', 'byte_size' => strlen('Fictional immutable observation'), 'sha256' => hash('sha256', 'Fictional immutable observation')]);
    PayRecord::factory()->create(['employee_id' => $employee->employee_id, 'source' => 'Restricted pay evidence']);
    OperationalRecord::factory()->create(['employee_id' => $employee->employee_id, 'notes' => 'Restricted context evidence']);
    reviewSignIn($manager);
    $this->post('/reviews/'.$review->id.'/submit', submissionTokens($review))->assertSessionHasNoErrors();
    $submission = PdrSubmission::query()->sole();
    $snapshot = $submission->snapshot;
    $path = $snapshot['assessments'][0]['attachments'][0]['storage_path'];
    expect($path)->not->toBe('original/private.txt');
    Storage::disk('evidence')->delete('original/private.txt');
    $employee->employee->update(['name' => 'Later changed name']);
    expect(Storage::disk('evidence')->get($path))->toBe('Fictional immutable observation')->and($submission->fresh()->snapshot)->toEqual($snapshot);
    $response = $this->get('/reviews/'.$review->id.'/submissions/'.$submission->id)->assertOk()->assertDontSee('Restricted pay evidence')->assertDontSee('Restricted context evidence')->assertSee('Private observation evidence')->assertSee('Supported practice')->assertSee('Submitted achievement examples')->assertSee('Submitted coaching request');
    expect($response->viewData('page')['assessments'][0]['skill_name'])->toBe($snapshot['framework']['definition']['components'][0]['skills'][0]['name']);
    expect(app(ScoreCalculator::class)->calculate($snapshot['framework']['definition'], $snapshot['score_inputs'])['score'])->toEqual($snapshot['score']['score']);
    expect(json_encode($response->viewData('page')))->not->toContain('Restricted pay evidence', 'Restricted context evidence', 'storage_path');
    $this->get('/reviews/'.$review->id.'/submissions/'.$submission->id.'/evidence/'.$revision->attachments()->sole()->id)->assertOk()->assertDownload('observation.txt');
    reviewSignIn($employee);
    $this->get('/reviews/'.$review->id.'/submissions/'.$submission->id)->assertForbidden();
    $this->get('/reviews/'.$review->id.'/submissions/'.$submission->id.'/evidence/'.$revision->attachments()->sole()->id)->assertForbidden();
});

it('returns to a distinct successor and preserves submission lineage through HR review', function (): void {
    [$hr,$manager,$employee,$cycle,$review] = submissionFixture();
    reviewSignIn($manager);
    $this->post('/reviews/'.$review->id.'/submit', submissionTokens($review))->assertSessionHasNoErrors();
    $first = PdrSubmission::query()->sole();
    $lockedDraft = $review->refresh()->current_draft_id;
    reviewSignIn($hr);
    $url = '/reviews/'.$review->id.'/submissions/'.$first->id.'/hr';
    $this->postJson($url, ['expected_version' => $review->version, 'action' => 'return', 'reason' => ''])->assertUnprocessable();
    $token = $review->version;
    $this->post($url, ['expected_version' => $token, 'action' => 'return', 'reason' => 'Clarify the observed examples'])->assertSessionHasNoErrors();
    expect($review->refresh()->state->value)->toBe('draft')->and($review->current_draft_id)->not->toBe($lockedDraft)->and($review->currentDraft->previous_submission_id)->toBe($first->id);
    $this->postJson($url, ['expected_version' => $token, 'action' => 'review', 'reason' => 'Stale'])->assertStatus(409);
    reviewSignIn($manager);
    $this->post('/reviews/'.$review->id.'/submit', submissionTokens($review))->assertSessionHasNoErrors();
    $second = PdrSubmission::query()->latest('id')->firstOrFail();
    expect($second->submission_number)->toBe(2)->and($second->previous_submission_id)->toBe($first->id)->and($first->fresh()->snapshot)->toBe($first->snapshot);
    reviewSignIn($hr);
    $this->post('/reviews/'.$review->id.'/submissions/'.$second->id.'/hr', ['expected_version' => $review->refresh()->version, 'action' => 'review', 'reason' => 'Evidence checked'])->assertSessionHasNoErrors();
    expect($review->refresh()->state->value)->toBe('hr_reviewed')->and(PdrTransition::query()->where('action', 'hr_reviewed')->count())->toBe(1);
});

it('denies self or submitting manager HR review and rechecks revoked authority', function (): void {
    [$hr,$manager,$employee,$cycle,$review] = submissionFixture();
    $manager->forceFill(['capabilities' => [Capability::AdministerReviews->value]])->save();
    reviewSignIn($manager);
    $this->post('/reviews/'.$review->id.'/submit', submissionTokens($review))->assertSessionHasNoErrors();
    $submission = PdrSubmission::query()->sole();
    $url = '/reviews/'.$review->id.'/submissions/'.$submission->id.'/hr';
    $this->postJson($url, ['expected_version' => $review->refresh()->version, 'action' => 'review', 'reason' => 'Self check'])->assertForbidden();
    reviewSignIn($hr);
    $hr->forceFill(['capabilities' => [Capability::ViewOrganisation->value]])->save();
    $this->postJson($url, ['expected_version' => $review->version, 'action' => 'review', 'reason' => 'Revoked'])->assertForbidden();
    expect($review->refresh()->state->value)->toBe('submitted');
});

it('blocks required evidence lost between scoring capture and the private copy', function (): void {
    Storage::fake('evidence');
    [$hr,$manager,$employee,$cycle,$review,$assessment,$revision] = submissionFixture();
    AssessmentRevision::query()->whereKey($revision->id)->update(['evidence_notes' => null]);
    EvidenceAttachment::factory()->create(['assessment_revision_id' => $revision->id]);
    $availability = Mockery::mock(EvidenceAvailability::class);
    $availability->shouldReceive('isIntact')->twice()->andReturn(true, false);
    app()->instance(EvidenceAvailability::class, $availability);
    reviewSignIn($manager);
    $this->postJson('/reviews/'.$review->id.'/submit', submissionTokens($review))->assertUnprocessable();
    expect(PdrSubmission::query()->count())->toBe(0)->and($review->currentDraft->fresh()->locked_at)->toBeNull();
});

it('shows HR return instructions to the assigned manager while keeping them private from the employee', function (): void {
    [$hr,$manager,$employee,$cycle,$review] = submissionFixture();
    reviewSignIn($manager);
    $this->post('/reviews/'.$review->id.'/submit', submissionTokens($review))->assertSessionHasNoErrors();
    $submission = PdrSubmission::query()->sole();
    reviewSignIn($hr);
    $this->post('/reviews/'.$review->id.'/submissions/'.$submission->id.'/hr', ['expected_version' => $review->refresh()->version, 'action' => 'return', 'reason' => 'Internal correction instructions for the fictional manager'])->assertSessionHasNoErrors();
    reviewSignIn($manager);
    $response = $this->get('/reviews/'.$review->id)->assertOk()->assertSee('Internal correction instructions for the fictional manager');
    expect($response->viewData('page')['transitions'][0]['actor'])->toContain($hr->name);
    expect($response->viewData('page')['transitions'][0]['submission_number'])->toBe(1);
    reviewSignIn($employee);
    $response = $this->get('/reviews/'.$review->id)->assertOk()->assertDontSee('Internal correction instructions for the fictional manager');
    expect(json_encode($response->viewData('page')))->not->toContain('Internal correction instructions');
});
