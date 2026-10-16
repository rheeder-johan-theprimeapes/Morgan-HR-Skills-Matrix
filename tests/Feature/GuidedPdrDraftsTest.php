<?php

declare(strict_types=1);

use App\Actions\AllocatePdrReview;
use App\Capability;
use App\Models\AuditEvent;
use App\Models\Employee;
use App\Models\JobRole;
use App\Models\PayBenchmark;
use App\Models\ReviewCycle;
use App\Models\ScoringFramework;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function pdrDraftFixture(string $profile = 'production'): array
{
    $hr = User::factory()->withEmployee()->create(['capabilities' => [Capability::AdministerReviews->value, Capability::ViewOrganisation->value]]);
    $manager = User::factory()->withEmployee()->create();
    $employee = User::factory()->withEmployee()->create();
    $employee->employee->forceFill(['manager_id' => $manager->employee_id])->save();
    $cycle = ReviewCycle::factory()->create();
    $review = app(AllocatePdrReview::class)->handle($hr, $cycle, ['employee_id' => $employee->employee_id, 'manager_user_id' => $manager->id, 'section_profile' => $profile, 'expected_version' => 0]);

    return [$hr, $manager, $employee, $cycle, $review];
}

it('creates a published-framework cycle and explicitly allocates role appropriate review sections', function (string $profile, string $prompt): void {
    [$hr, $manager, $employee, $cycle, $review] = pdrDraftFixture($profile);
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->get('/review-cycles')->assertOk();
    $this->get('/review-cycles/'.$cycle->id)->assertOk()->assertSee($employee->employee->name);
    $this->actingAs($manager)->withSession(['account_session_version' => $manager->session_version]);
    $this->get('/reviews')->assertOk()->assertSee($employee->employee->name);
    $this->get('/reviews/'.$review->id)->assertOk()->assertSee($prompt)->assertSee('Provisional')->assertSee('Saved');
    expect($review->currentDraft->revision)->toBe(1)->and($review->section_profile)->toBe($profile);
})->with([['production', 'Safe working and process observations'], ['office', 'Accuracy and service observations'], ['leadership', 'Coaching and team outcomes']]);

it('creates cycles only with explicit review authority and published versions', function (): void {
    $hr = User::factory()->withEmployee()->create(['capabilities' => [Capability::AdministerReviews->value]]);
    $framework = ScoringFramework::factory()->create();
    $input = ['code' => 'DEMO-CYCLE', 'name' => 'Illustrative cycle', 'scoring_framework_id' => $framework->id, 'opens_on' => today()->toDateString(), 'closes_on' => today()->addMonth()->toDateString(), 'requires_employee_input' => true];
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->post('/review-cycles', $input)->assertSessionHasErrors('scoring_framework_id');
    $framework->update(['published_at' => now()]);
    $this->post('/review-cycles', $input)->assertSessionHasNoErrors();
    expect(ReviewCycle::query()->count())->toBe(1);
    $ordinary = User::factory()->directoryAdministrator()->create();
    $this->actingAs($ordinary)->withSession(['account_session_version' => $ordinary->session_version]);
    $this->get('/review-cycles')->assertForbidden();
    $this->post('/review-cycles', $input)->assertForbidden();
});

it('saves independent owned sections and returns only safe autosave acknowledgement', function (): void {
    [$hr, $manager, $employee, $cycle, $review] = pdrDraftFixture();
    $this->actingAs($employee)->withSession(['account_session_version' => $employee->session_version]);
    $this->postJson('/reviews/'.$review->id.'/draft', ['section' => 'employee', 'expected_revision' => 1, 'employee_section' => ['reflection' => 'Fictional self reflection', 'achievements' => 'Fictional example', 'development_needs' => 'Training suggestion']])->assertOk()->assertExactJson(['status' => 'saved', 'revision' => 2]);
    $this->actingAs($manager)->withSession(['account_session_version' => $manager->session_version]);
    $this->postJson('/reviews/'.$review->id.'/draft', ['section' => 'manager', 'expected_revision' => 2, 'manager_section' => ['summary' => 'Confidential manager calibration', 'role_notes' => 'Private internal detail', 'objectives' => [['description' => 'Illustrative goal', 'measure' => 'Observed example', 'due_on' => '2027-01-01']]]])->assertOk()->assertExactJson(['status' => 'saved', 'revision' => 3]);
    $draft = $review->currentDraft->fresh();
    expect($draft->employee_section['reflection'])->toBe('Fictional self reflection')->and($draft->manager_section['summary'])->toBe('Confidential manager calibration');
    $this->actingAs($employee)->withSession(['account_session_version' => $employee->session_version]);
    $response = $this->get('/reviews/'.$review->id)->assertOk()->assertSee('Fictional self reflection')->assertDontSee('Confidential manager calibration')->assertDontSee('Private internal detail');
    expect(json_encode($response->viewData('page')))->not->toContain('Confidential manager calibration', 'Private internal detail');
});

it('rejects cross section or forged confidential employee fields without writes', function (): void {
    [$hr, $manager, $employee, $cycle, $review] = pdrDraftFixture();
    $this->actingAs($employee)->withSession(['account_session_version' => $employee->session_version]);
    $this->postJson('/reviews/'.$review->id.'/draft', ['section' => 'manager', 'expected_revision' => 1, 'manager_section' => ['summary' => 'Forged']])->assertForbidden();
    $this->postJson('/reviews/'.$review->id.'/draft', ['section' => 'employee', 'expected_revision' => 1, 'employee_section' => ['reflection' => 'Allowed'], 'pay_proposal' => ['outcome' => 'propose', 'amount' => '99999.00']])->assertUnprocessable()->assertJsonValidationErrors('pay_proposal');
    $this->postJson('/reviews/'.$review->id.'/draft', ['section' => 'employee', 'expected_revision' => 1, 'employee_section' => ['reflection' => 'Allowed', 'manager_summary' => 'Smuggled']])->assertUnprocessable();
    expect($review->currentDraft->fresh()->revision)->toBe(1);
});

it('keeps administrator inspection read only and denies unrelated or former managers', function (): void {
    [$hr, $manager, $employee, $cycle, $review] = pdrDraftFixture();
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->get('/reviews/'.$review->id)->assertOk();
    $this->postJson('/reviews/'.$review->id.'/draft', ['section' => 'manager', 'expected_revision' => 1, 'manager_section' => ['summary' => 'Impersonation']])->assertForbidden();
    $other = User::factory()->withEmployee()->create();
    $this->actingAs($other)->withSession(['account_session_version' => $other->session_version]);
    $this->get('/reviews/'.$review->id)->assertForbidden();
    $employee->employee->forceFill(['manager_id' => null])->save();
    $this->actingAs($manager)->withSession(['account_session_version' => $manager->session_version]);
    $this->get('/reviews/'.$review->id)->assertForbidden();
    $this->postJson('/reviews/'.$review->id.'/draft', ['section' => 'manager', 'expected_revision' => 1, 'manager_section' => []])->assertForbidden();
});

it('detects stale draft writes and preserves the original form token', function (): void {
    [$hr, $manager, $employee, $cycle, $review] = pdrDraftFixture();
    $this->actingAs($employee)->withSession(['account_session_version' => $employee->session_version]);
    $input = ['section' => 'employee', 'expected_revision' => 1, 'employee_section' => ['reflection' => 'First saved version']];
    $this->postJson('/reviews/'.$review->id.'/draft', $input)->assertOk();
    $input['employee_section']['reflection'] = 'Retained unsaved stale reflection';
    $this->postJson('/reviews/'.$review->id.'/draft', $input)->assertUnprocessable()->assertJsonValidationErrors('expected_revision');
    $this->from('/reviews/'.$review->id)->post('/reviews/'.$review->id.'/draft', $input)->assertSessionHasErrors('expected_revision');
    $response = $this->get('/reviews/'.$review->id)->assertOk()->assertSee('Retained unsaved stale reflection');
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    expect((new DOMXPath($document))->query('//input[@name="expected_revision"]')->item(0)->getAttribute('value'))->toBe('1');
    expect($review->currentDraft->fresh()->employee_section['reflection'])->toBe('First saved version');
});

it('requires explicit recommendation authority and keeps proposals out of employee responses', function (): void {
    [$hr, $manager, $employee, $cycle, $review] = pdrDraftFixture();
    $input = ['section' => 'manager', 'expected_revision' => 1, 'manager_section' => ['summary' => 'Example'], 'pay_proposal' => ['outcome' => 'propose', 'amount' => '51000.00', 'currency' => 'GBP', 'basis' => 'annual', 'effective_on' => '2027-01-01', 'reason' => 'Private fictional proposal rationale']];
    $this->actingAs($manager)->withSession(['account_session_version' => $manager->session_version]);
    $this->postJson('/reviews/'.$review->id.'/draft', $input)->assertUnprocessable()->assertJsonValidationErrors('pay_proposal');
    $manager->forceFill(['capabilities' => [Capability::RecommendPay->value]])->save();
    $this->postJson('/reviews/'.$review->id.'/draft', $input)->assertOk();
    expect($review->currentDraft->fresh()->pay_proposal['amount_minor'])->toBe(5100000);
    $this->actingAs($employee)->withSession(['account_session_version' => $employee->session_version]);
    $this->get('/reviews/'.$review->id)->assertOk()->assertDontSee('51000')->assertDontSee('Private fictional proposal rationale')->assertDontSee('pay_proposal');
});

it('rejects allocation without current supervision duplicate allocation or self review', function (): void {
    [$hr, $manager, $employee, $cycle, $review] = pdrDraftFixture();
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->post('/review-cycles/'.$cycle->id.'/allocations', ['employee_id' => $employee->employee_id, 'manager_user_id' => $manager->id, 'section_profile' => 'office', 'expected_version' => 0])->assertSessionHasErrors('employee_id');
    $other = Employee::factory()->create();
    $this->post('/review-cycles/'.$cycle->id.'/allocations', ['employee_id' => $other->id, 'manager_user_id' => $manager->id, 'section_profile' => 'office', 'expected_version' => 0])->assertSessionHasErrors('manager_user_id');
    $this->post('/review-cycles/'.$cycle->id.'/allocations', ['employee_id' => $manager->employee_id, 'manager_user_id' => $manager->id, 'section_profile' => 'office', 'expected_version' => 0])->assertSessionHasErrors('manager_user_id');
});

it('denies saves outside cycle dates or after a draft is locked', function (): void {
    [$hr, $manager, $employee, $cycle, $review] = pdrDraftFixture();
    $this->actingAs($employee)->withSession(['account_session_version' => $employee->session_version]);
    $input = ['section' => 'employee', 'expected_revision' => 1, 'employee_section' => ['reflection' => 'Attempt']];
    $cycle->update(['closes_on' => today()->subDay()->toDateString()]);
    $this->postJson('/reviews/'.$review->id.'/draft', $input)->assertStatus(409);
    $cycle->update(['closes_on' => today()->addMonth()->toDateString()]);
    $review->currentDraft->update(['locked_at' => now()]);
    $this->postJson('/reviews/'.$review->id.'/draft', $input)->assertStatus(409);
});

it('reassigns a draft only with current supervision a reason and the current allocation token', function (): void {
    [$hr, $manager, $employee, $cycle, $review] = pdrDraftFixture();
    $replacement = User::factory()->withEmployee()->create();
    $employee->employee->forceFill(['manager_id' => $replacement->employee_id])->save();
    $input = ['employee_id' => $employee->employee_id, 'manager_user_id' => $replacement->id, 'section_profile' => 'office', 'expected_version' => 1];
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->put('/reviews/'.$review->id.'/allocation', $input)->assertSessionHasErrors('reason');
    $input['reason'] = 'Fictional reporting-line change';
    $this->put('/reviews/'.$review->id.'/allocation', $input)->assertSessionHasNoErrors();
    expect($review->refresh()->manager_user_id)->toBe($replacement->id)->and($review->version)->toBe(2);
    expect(AuditEvent::query()->where('event', 'review.allocated')->latest('id')->firstOrFail()->details['reason'])->toBe('Fictional reporting-line change');
    $this->put('/reviews/'.$review->id.'/allocation', $input)->assertSessionHasErrors('expected_version');
    $this->actingAs($manager)->withSession(['account_session_version' => $manager->session_version]);
    $this->get('/reviews/'.$review->id)->assertForbidden();
    $this->actingAs($replacement)->withSession(['account_session_version' => $replacement->session_version]);
    $this->get('/reviews/'.$review->id)->assertOk()->assertSee('Accuracy and service observations');
});

it('preserves the allocated framework and rejects stale cycle setting updates', function (): void {
    [$hr, $manager, $employee, $cycle, $review] = pdrDraftFixture();
    $input = ['name' => 'Updated cycle dates', 'scoring_framework_id' => $cycle->scoring_framework_id, 'opens_on' => $cycle->opens_on->toDateString(), 'closes_on' => $cycle->closes_on->toDateString(), 'requires_employee_input' => true, 'expected_revision' => 1];
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->put('/review-cycles/'.$cycle->id, $input)->assertSessionHasNoErrors();
    $this->put('/review-cycles/'.$cycle->id, $input)->assertSessionHasErrors('expected_revision');
    $other = ScoringFramework::factory()->create(['published_at' => now()]);
    $input['expected_revision'] = 2;
    $input['scoring_framework_id'] = $other->id;
    $this->put('/review-cycles/'.$cycle->id, $input)->assertSessionHasErrors('scoring_framework_id');
    expect($cycle->refresh()->scoring_framework_id)->not->toBe($other->id);
});

it('retains stale reassignment input with its old allocation token until reload', function (): void {
    [$hr, $manager, $employee, $cycle, $review] = pdrDraftFixture();
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $input = ['employee_id' => $employee->employee_id, 'manager_user_id' => $manager->id, 'section_profile' => 'office', 'expected_version' => 1, 'reason' => 'First explicit profile correction'];
    $this->put('/reviews/'.$review->id.'/allocation', $input)->assertSessionHasNoErrors();
    $input['section_profile'] = 'leadership';
    $input['reason'] = 'Retained unsaved reassignment rationale';
    $url = '/review-cycles/'.$cycle->id;
    $this->from($url)->put('/reviews/'.$review->id.'/allocation', $input)->assertSessionHasErrors('expected_version');
    $response = $this->get($url)->assertOk()->assertSee('Retained unsaved reassignment rationale');
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    expect((new DOMXPath($document))->query('//form[contains(@action,"/reviews/'.$review->id.'/allocation")]/input[@name="expected_version"]')->item(0)->getAttribute('value'))->toBe('1');
});

it('rejects rationale without an outcome instead of acknowledging discarded proposal input', function (): void {
    [$hr, $manager, $employee, $cycle, $review] = pdrDraftFixture();
    $manager->forceFill(['capabilities' => [Capability::RecommendPay->value]])->save();
    $this->actingAs($manager)->withSession(['account_session_version' => $manager->session_version]);
    $input = ['section' => 'manager', 'expected_revision' => 1, 'manager_section' => ['summary' => 'Fictional summary'], 'pay_proposal' => ['outcome' => null, 'reason' => 'Rationale typed before selecting an outcome']];
    $this->postJson('/reviews/'.$review->id.'/draft', $input)->assertUnprocessable()->assertJsonValidationErrors('pay_proposal.outcome');
    expect($review->currentDraft->fresh()->revision)->toBe(1);
    $input['pay_proposal']['outcome'] = 'no_change';
    $this->postJson('/reviews/'.$review->id.'/draft', $input)->assertOk();
    expect($review->currentDraft->fresh()->pay_proposal['reason'])->toBe('Rationale typed before selecting an outcome');
});

it('preserves a saved citation when benchmark authority is revoked but recommendation authority remains', function (): void {
    [$hr, $manager, $employee, $cycle, $review] = pdrDraftFixture();
    $role = JobRole::factory()->create();
    $employee->employee->forceFill(['job_role_id' => $role->id])->save();
    $benchmark = PayBenchmark::factory()->create(['job_role_id' => $role->id]);
    $manager->forceFill(['capabilities' => [Capability::RecommendPay->value, Capability::Benchmarks->value]])->save();
    $this->actingAs($manager)->withSession(['account_session_version' => $manager->session_version]);
    $input = ['section' => 'manager', 'expected_revision' => 1, 'manager_section' => ['summary' => 'Fictional summary'], 'pay_proposal' => ['outcome' => 'propose', 'amount' => '51000.00', 'currency' => 'GBP', 'basis' => 'annual', 'effective_on' => '2027-01-01', 'reason' => 'Fictional source-supported rationale', 'benchmark_id' => $benchmark->id]];
    $this->postJson('/reviews/'.$review->id.'/draft', $input)->assertOk();
    $manager->forceFill(['capabilities' => [Capability::RecommendPay->value]])->save();
    unset($input['pay_proposal']['benchmark_id']);
    $input['expected_revision'] = 2;
    $input['manager_section']['summary'] = 'Ordinary summary autosave after permission change';
    $this->postJson('/reviews/'.$review->id.'/draft', $input)->assertOk();
    expect($review->currentDraft->fresh()->pay_proposal['benchmark_id'])->toBe($benchmark->id);
    $response = $this->get('/reviews/'.$review->id)->assertOk();
    expect(isset($response->viewData('page')['pay_proposal']['benchmark_id']))->toBeFalse();
});
