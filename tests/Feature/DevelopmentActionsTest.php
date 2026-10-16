<?php

declare(strict_types=1);
use App\Actions\SaveDevelopmentAction;
use App\AssessmentKind;
use App\Capability;
use App\Models\Assessment;
use App\Models\AssessmentRevision;
use App\Models\AuditEvent;
use App\Models\DevelopmentAction;
use App\Models\DevelopmentRevision;
use App\Models\Employee;
use App\Models\PdrDecision;
use App\Models\PdrDraft;
use App\Models\PdrFeedback;
use App\Models\PdrRelease;
use App\Models\PdrReview;
use App\Models\PdrSubmission;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);
function developmentFixture(): array
{
    $manager = User::factory()->withEmployee()->create();
    $employee = User::factory()->withEmployee()->create();
    $employee->employee->forceFill(['manager_id' => $manager->employee_id])->save();

    return [$manager, $employee];
}
function developmentPlan(): array
{
    return ['mode' => 'plan', 'expected_revision' => 0, 'title' => 'Fictional supported practice', 'description' => 'Practise the agreed procedure with coaching', 'starts_on' => today()->toDateString(), 'due_on' => today()->addMonth()->toDateString(), 'resource_label' => 'Public training resource', 'resource_url' => 'https://example.com/learning', 'origin_kind' => 'manual'];
}
function developmentSignIn(User $actor): void
{
    test()->actingAs($actor)->withSession(['account_session_version' => $actor->session_version]);
}
function allocatedDevelopmentFixture(): array
{
    [$manager,$employee] = developmentFixture();
    $action = app(SaveDevelopmentAction::class)->handle($manager, $employee->employee, null, developmentPlan());

    return [$manager, $employee, $action];
}

it('creates an owned skill-linked plan and lets the employee follow intended progress', function (): void {
    [$manager,$employee] = developmentFixture();
    $skill = Skill::factory()->create();
    $input = developmentPlan();
    $input['origin_kind'] = 'skill';
    $input['origin_skill_id'] = $skill->id;
    developmentSignIn($manager);
    $this->post('/employees/'.$employee->employee_id.'/development', $input)->assertSessionHasNoErrors()->assertRedirect();
    $action = DevelopmentAction::query()->sole();
    expect($action->employee_id)->toBe($employee->employee_id)->and($action->revision)->toBe(1);
    $this->get('/development-actions/'.$action->id)->assertOk()->assertSee($skill->name)->assertSee('https://example.com/learning');
    developmentSignIn($employee);
    $this->get('/employees/'.$employee->employee_id.'/development')->assertOk()->assertSee('Fictional supported practice');
    $this->post('/development-actions/'.$action->id, ['mode' => 'progress', 'expected_revision' => 1, 'state' => 'in_progress', 'notes' => 'Fictional coaching started'])->assertSessionHasNoErrors();
    expect($action->refresh()->revision)->toBe(2)->and($action->currentRevision->state->value)->toBe('in_progress')->and(DevelopmentRevision::query()->count())->toBe(2);
});

it('denies outside scope assignment viewing and employee plan administration', function (): void {
    [$manager,$employee,$action] = allocatedDevelopmentFixture();
    $other = Employee::factory()->create();
    developmentSignIn($manager);
    $this->post('/employees/'.$other->id.'/development', developmentPlan())->assertForbidden();
    $this->get('/employees/'.$other->id.'/development')->assertForbidden();
    developmentSignIn($employee);
    $this->post('/employees/'.$employee->employee_id.'/development', developmentPlan())->assertForbidden();
    $update = developmentPlan();
    $update['expected_revision'] = 1;
    unset($update['origin_kind']);
    $this->post('/development-actions/'.$action->id, $update)->assertForbidden();
    $reader = User::factory()->withEmployee()->create(['capabilities' => [Capability::ViewOrganisation->value]]);
    developmentSignIn($reader);
    $this->get('/development-actions/'.$action->id)->assertForbidden();
});

it('keeps source manager assessment content out of the employee action projection', function (): void {
    [$manager,$employee] = developmentFixture();
    $skill = Skill::factory()->create();
    $assessment = Assessment::factory()->create(['employee_id' => $employee->employee_id, 'skill_id' => $skill->id, 'kind' => AssessmentKind::Manager]);
    $revision = AssessmentRevision::factory()->create(['assessment_id' => $assessment->id, 'comment' => 'Secret manager calibration', 'evidence_notes' => 'Private original evidence']);
    $input = developmentPlan();
    $input['origin_kind'] = 'skill';
    $input['origin_skill_id'] = $skill->id;
    $input['origin_assessment_revision_id'] = $revision->id;
    developmentSignIn($manager);
    $this->post('/employees/'.$employee->employee_id.'/development', $input)->assertSessionHasNoErrors();
    $action = DevelopmentAction::query()->sole();
    developmentSignIn($employee);
    $response = $this->get('/development-actions/'.$action->id)->assertOk()->assertDontSee('Secret manager calibration')->assertDontSee('Private original evidence');
    expect(json_encode($response->viewData('page')))->not->toContain('Secret manager calibration', 'Private original evidence', 'origin_assessment_revision_id');
});

it('rejects forged cross-person origins and unsafe resource or date values atomically', function (string $invalid): void {
    [$manager,$employee] = developmentFixture();
    $input = developmentPlan();
    if ($invalid === 'link') {
        $input['resource_url'] = 'javascript:alert(1)';
    }
    if ($invalid === 'credentials') {
        $input['resource_url'] = 'https://secret:password@example.com/course';
    }
    if ($invalid === 'dates') {
        $input['due_on'] = today()->subDay()->toDateString();
    }
    if ($invalid === 'label') {
        $input['resource_label'] = '';
    }
    if ($invalid === 'assessment') {
        $revision = AssessmentRevision::factory()->create();
        $input['origin_kind'] = 'skill';
        $input['origin_skill_id'] = $revision->assessment->skill_id;
        $input['origin_assessment_revision_id'] = $revision->id;
    }
    if ($invalid === 'review') {
        $input['origin_kind'] = 'review';
        $input['origin_release_id'] = 999999;
        $input['origin_objective_index'] = 0;
    }
    developmentSignIn($manager);
    $this->postJson('/employees/'.$employee->employee_id.'/development', $input)->assertUnprocessable();
    expect(DevelopmentAction::query()->count())->toBe(0);
})->with(['link', 'credentials', 'dates', 'label', 'assessment', 'review']);

it('requires dated completion evidence and audits privately authorised downloads', function (): void {
    Storage::fake('evidence');
    [$manager,$employee,$action] = allocatedDevelopmentFixture();
    developmentSignIn($employee);
    $input = ['mode' => 'progress', 'expected_revision' => 1, 'state' => 'completed', 'completed_on' => today()->toDateString(), 'notes' => ''];
    $this->postJson('/development-actions/'.$action->id, $input)->assertUnprocessable();
    $file = UploadedFile::fake()->createWithContent('fictional-completion.pdf', "%PDF-1.4\nFictional completion evidence");
    $this->post('/development-actions/'.$action->id, $input + ['attachment' => $file])->assertSessionHasNoErrors();
    $revision = $action->refresh()->currentRevision;
    expect($revision->state->value)->toBe('completed')->and(Storage::disk('evidence')->exists($revision->storage_path))->toBeTrue();
    $this->get('/development-revisions/'.$revision->id.'/evidence')->assertOk()->assertDownload('fictional-completion.pdf');
    expect(AuditEvent::query()->where('event', 'development.evidence.downloaded')->count())->toBe(1);
    $other = User::factory()->withEmployee()->create();
    developmentSignIn($other);
    $this->get('/development-revisions/'.$revision->id.'/evidence')->assertForbidden();
    Storage::disk('evidence')->put($revision->storage_path, 'Changed bytes');
    developmentSignIn($employee);
    $this->get('/development-revisions/'.$revision->id.'/evidence')->assertStatus(409);
});

it('preserves stale input and cleans an unreferenced uploaded file after rejected progress', function (): void {
    Storage::fake('evidence');
    [$manager,$employee,$action] = allocatedDevelopmentFixture();
    developmentSignIn($employee);
    $url = '/development-actions/'.$action->id;
    $input = ['mode' => 'progress', 'expected_revision' => 1, 'state' => 'in_progress', 'notes' => 'First observed coaching'];
    $this->post($url, $input)->assertSessionHasNoErrors();
    $input['notes'] = 'Retained stale progress text';
    $input['attachment'] = UploadedFile::fake()->createWithContent('stale.pdf', "%PDF-1.4\nStale fictional evidence");
    $this->from($url)->post($url, $input)->assertSessionHasErrors('expected_revision');
    $response = $this->get($url)->assertOk()->assertSee('Retained stale progress text');
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    expect((new DOMXPath($document))->query('//form[contains(@action,"/development-actions/")]/input[@name="expected_revision"]')->item(0)->getAttribute('value'))->toBe('1');
    expect(Storage::disk('evidence')->allFiles('development'))->toBe([])->and($action->fresh()->currentRevision->notes)->toBe('First observed coaching');
});

it('locks terminal plans and permits only reasoned supervisor reopening or cancellation', function (): void {
    [$manager,$employee,$action] = allocatedDevelopmentFixture();
    developmentSignIn($employee);
    $url = '/development-actions/'.$action->id;
    $this->post($url, ['mode' => 'progress', 'expected_revision' => 1, 'state' => 'completed', 'completed_on' => today()->toDateString(), 'notes' => 'Fictional observed completion'])->assertSessionHasNoErrors();
    $this->postJson($url, ['mode' => 'progress', 'expected_revision' => 2, 'state' => 'in_progress', 'notes' => 'Employee attempted reopen'])->assertForbidden();
    developmentSignIn($manager);
    $this->postJson($url, ['mode' => 'progress', 'expected_revision' => 2, 'state' => 'in_progress', 'notes' => 'Further coaching'])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->post($url, ['mode' => 'progress', 'expected_revision' => 2, 'state' => 'in_progress', 'notes' => 'Further coaching', 'reason' => 'Fictional reassessment requested'])->assertSessionHasNoErrors();
    expect($action->refresh()->revision)->toBe(3)->and(DevelopmentRevision::query()->where('state', 'completed')->count())->toBe(1);
    $this->post($url, ['mode' => 'progress', 'expected_revision' => 3, 'state' => 'cancelled', 'notes' => '', 'reason' => 'Fictional plan superseded'])->assertSessionHasNoErrors();
    expect($action->refresh()->currentRevision->state->value)->toBe('cancelled');
});

it('rechecks reporting changes before an existing supervisor can update a plan', function (): void {
    [$manager,$employee,$action] = allocatedDevelopmentFixture();
    developmentSignIn($manager);
    $employee->employee->forceFill(['manager_id' => null])->save();
    $this->postJson('/development-actions/'.$action->id, ['mode' => 'progress', 'expected_revision' => 1, 'state' => 'in_progress', 'notes' => 'Denied former supervisor'])->assertForbidden();
    expect($action->fresh()->revision)->toBe(1);
});

it('creates from a same-person released objective through an ordinary form choice', function (): void {
    [$manager,$employee] = developmentFixture();
    $review = PdrReview::factory()->create(['employee_id' => $employee->employee_id]);
    $draft = PdrDraft::factory()->create(['pdr_review_id' => $review->id]);
    $submission = PdrSubmission::factory()->create(['pdr_draft_id' => $draft->id]);
    $decision = PdrDecision::factory()->create(['pdr_submission_id' => $submission->id]);
    $feedback = PdrFeedback::factory()->create(['pdr_decision_id' => $decision->id]);
    $release = PdrRelease::factory()->create(['pdr_feedback_id' => $feedback->id, 'summary' => ['goals' => [['description' => 'Released coaching objective', 'measure' => 'Two examples', 'due_on' => '2027-01-15']], 'final_pay' => ['outcome' => 'change', 'amount_minor' => 123456789]]]);
    $input = developmentPlan();
    $input['origin_kind'] = 'review';
    $input['origin_objective'] = $release->id.':0';
    developmentSignIn($manager);
    $this->post('/employees/'.$employee->employee_id.'/development', $input)->assertSessionHasNoErrors();
    $action = DevelopmentAction::query()->sole();
    expect($action->origin_release_id)->toBe($release->id)->and($action->origin_objective_index)->toBe(0);
    developmentSignIn($employee);
    $this->get('/development-actions/'.$action->id)->assertOk()->assertSee('Released coaching objective')->assertDontSee('123456789');
});

it('denies backward owner status changes and requires a reasoned supervisor replan', function (): void {
    [$manager,$employee,$action] = allocatedDevelopmentFixture();
    developmentSignIn($employee);
    $url = '/development-actions/'.$action->id;
    $this->post($url, ['mode' => 'progress', 'expected_revision' => 1, 'state' => 'in_progress', 'notes' => 'Learning started'])->assertSessionHasNoErrors();
    $this->postJson($url, ['mode' => 'progress', 'expected_revision' => 2, 'state' => 'planned', 'notes' => 'Attempted rewind'])->assertForbidden();
    developmentSignIn($manager);
    $this->postJson($url, ['mode' => 'progress', 'expected_revision' => 2, 'state' => 'planned', 'notes' => 'Replan'])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->post($url, ['mode' => 'progress', 'expected_revision' => 2, 'state' => 'planned', 'notes' => 'Replan', 'reason' => 'Fictional course rescheduled'])->assertSessionHasNoErrors();
});

it('rejects unsafe completion files without metadata or storage leftovers', function (string $invalid): void {
    Storage::fake('evidence');
    [$manager,$employee,$action] = allocatedDevelopmentFixture();
    developmentSignIn($employee);
    $plainFake = UploadedFile::fake()->createWithContent('plain.txt', 'Plain text is not PDF');
    $file = match ($invalid) {
        'extension' => UploadedFile::fake()->createWithContent('payload.php', "%PDF-1.4\nFictional file"),'mime' => new UploadedFile($plainFake->getPathname(), 'fake.pdf', 'text/plain', null, true),'size' => UploadedFile::fake()->create('oversized.pdf', 10241, 'application/pdf')
    };
    $this->postJson('/development-actions/'.$action->id, ['mode' => 'progress', 'expected_revision' => 1, 'state' => 'in_progress', 'notes' => 'Valid progress text', 'attachment' => $file])->assertUnprocessable()->assertJsonValidationErrors('attachment');
    expect(DevelopmentRevision::query()->count())->toBe(1)->and(Storage::disk('evidence')->allFiles('development'))->toBe([]);
})->with(['extension', 'mime', 'size']);

it('cleans a partial completion-file write when storage reports failure', function (): void {
    $disk = Storage::fake('evidence');
    [$manager,$employee,$action] = allocatedDevelopmentFixture();
    developmentSignIn($employee);
    $broken = Mockery::mock(FilesystemAdapter::class);
    $broken->shouldReceive('putFileAs')->once()->andReturnUsing(function (string $directory, $file, string $name, array $options = []) use ($disk): bool {
        $disk->put($directory.'/'.$name, 'Partial fictional write');

        return false;
    });
    $broken->shouldReceive('delete')->andReturnUsing(fn (string $path): bool => $disk->delete($path));
    Storage::shouldReceive('disk')->with('evidence')->andReturn($broken);
    $this->postJson('/development-actions/'.$action->id, ['mode' => 'progress', 'expected_revision' => 1, 'state' => 'in_progress', 'notes' => 'Valid retained learning notes', 'attachment' => UploadedFile::fake()->createWithContent('completion.pdf', "%PDF-1.4\nFictional evidence")])->assertUnprocessable();
    expect($disk->allFiles('development'))->toBe([])->and(DevelopmentRevision::query()->count())->toBe(1);
});

it('retains rejected stale input when a competing update makes the action terminal', function (string $mode): void {
    [$manager,$employee,$action] = allocatedDevelopmentFixture();
    $url = '/development-actions/'.$action->id;
    developmentSignIn($mode === 'plan' ? $manager : $employee);
    $this->withCookie((string) config('session.cookie'), session()->getId());
    $this->get($url)->assertOk();
    app(SaveDevelopmentAction::class)->handle($manager, $employee->employee, $action, ['mode' => 'progress', 'expected_revision' => 1, 'state' => 'completed', 'completed_on' => today()->toDateString(), 'notes' => 'First committed completion']);
    if ($mode === 'plan') {
        $input = developmentPlan();
        unset($input['origin_kind']);
        $input['expected_revision'] = 1;
        $input['description'] = 'Retained stale plan after terminal change';
    } else {
        $input = ['mode' => 'progress', 'expected_revision' => 1, 'state' => 'in_progress', 'notes' => 'Retained stale progress after terminal change'];
    }
    $this->from($url)->post($url, $input)->assertSessionHasErrors('expected_revision');

    $this->get($url)->assertOk()->assertSee($mode === 'plan' ? 'Retained stale plan after terminal change' : 'Retained stale progress after terminal change')->assertSee('Submitted revision 1');
    expect($action->fresh()->revision)->toBe(2);
})->with(['plan', 'progress']);

it('shows prior plan resources and completion dates after edits and reopening', function (): void {
    [$manager,$employee] = developmentFixture();
    $plan = developmentPlan();
    $plan['starts_on'] = today()->subDays(3)->toDateString();
    $plan['description'] = 'Original preserved coaching description';
    $action = app(SaveDevelopmentAction::class)->handle($manager, $employee->employee, null, $plan);
    $plan['expected_revision'] = 1;
    unset($plan['origin_kind']);
    $plan['description'] = 'Revised coaching description';
    $plan['resource_url'] = 'https://example.com/new-course';
    app(SaveDevelopmentAction::class)->handle($manager, $employee->employee, $action, $plan);
    $completed = today()->subDays(2)->toDateString();
    app(SaveDevelopmentAction::class)->handle($manager, $employee->employee, $action, ['mode' => 'progress', 'expected_revision' => 2, 'state' => 'completed', 'completed_on' => $completed, 'notes' => 'Observed completion']);
    app(SaveDevelopmentAction::class)->handle($manager, $employee->employee, $action, ['mode' => 'progress', 'expected_revision' => 3, 'state' => 'in_progress', 'notes' => 'Further practice', 'reason' => 'Fictional reassessment']);
    developmentSignIn($employee);
    $this->get('/development-actions/'.$action->id)->assertOk()->assertSee('Original preserved coaching description')->assertSee('https://example.com/learning')->assertSee('Completion recorded '.$completed);
});
