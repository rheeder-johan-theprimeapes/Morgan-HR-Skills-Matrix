<?php

declare(strict_types=1);
use App\Actions\DecidePdrSubmission;
use App\Actions\ReviewPdrSubmission;
use App\Actions\SubmitPdrReview;
use App\Capability;
use App\Models\PdrAcknowledgement;
use App\Models\PdrFeedback;
use App\Models\PdrRelease;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

require_once __DIR__.'/../Support/PdrFixtures.php';
uses(LazilyRefreshDatabase::class);
function feedbackFixture(): array
{
    [$hr,$manager,$employee,$cycle,$review] = submissionFixture();
    $review->currentDraft->update(['pay_proposal' => ['outcome' => 'propose', 'amount_minor' => 9999900, 'currency' => 'GBP', 'basis' => 'annual', 'effective_on' => '2027-01-01', 'reason' => 'Private manager salary proposal', 'benchmark_id' => null]]);
    $submission = app(SubmitPdrReview::class)->handle($manager, $review, submissionTokens($review));
    app(ReviewPdrSubmission::class)->handle($hr, $review, $submission, ['expected_version' => $review->refresh()->version, 'action' => 'review', 'reason' => 'Private HR calibration']);
    $slt = User::factory()->withEmployee()->create(['capabilities' => [Capability::DecideReviews->value, Capability::ApprovePay->value, Capability::ActualPay->value, Capability::ViewOrganisation->value]]);
    $decision = app(DecidePdrSubmission::class)->handle($slt, $review, $submission, ['expected_version' => $review->refresh()->version, 'action' => 'approve', 'reason' => 'Private SLT comparison rationale', 'pay_outcome' => 'change', 'amount' => '51000.00', 'currency' => 'GBP', 'basis' => 'annual', 'effective_on' => '2027-01-01']);

    return [$hr, $manager, $employee, $review, $submission, $decision];
}
function feedbackLogin(User $actor): void
{
    test()->actingAs($actor)->withSession(['account_session_version' => $actor->session_version]);
}
function feedbackInput($review): array
{
    return ['expected_version' => $review->refresh()->version, 'held_on' => today()->toDateString(), 'summary' => 'Employee-intended feedback summary', 'development_message' => 'Practise with coaching support'];
}

it('explicitly releases only the approved employee-safe outcome with the reusable allowlist', function (): void {
    [$hr,$manager,$employee,$review,$submission,$decision] = feedbackFixture();
    feedbackLogin($employee);
    $this->get('/reviews/'.$review->id.'/outcome')->assertForbidden();
    feedbackLogin($manager);
    $this->get('/reviews/'.$review->id.'/feedback')->assertOk()->assertDontSee('51000');
    $this->post('/reviews/'.$review->id.'/feedback', feedbackInput($review))->assertSessionHasNoErrors();
    $this->post('/reviews/'.$review->id.'/release', ['expected_version' => $review->refresh()->version])->assertSessionHasNoErrors();
    expect($review->refresh()->state->value)->toBe('released')->and(PdrRelease::query()->count())->toBe(1);
    $this->get('/reviews/'.$review->id.'/outcome')->assertForbidden();
    feedbackLogin($employee);
    $response = $this->get('/reviews/'.$review->id.'/outcome')->assertOk()->assertSee('Employee-intended feedback summary')->assertSee('Practise with coaching support')->assertSee('51000.00')->assertSee('Two observed demonstrations')->assertDontSee('99999')->assertDontSee('Private manager salary proposal')->assertDontSee('Private HR calibration')->assertDontSee('Private SLT comparison rationale')->assertDontSee('Internal manager summary')->assertDontSee('Private observation evidence');
    $page = $response->viewData('page');
    $encoded = json_encode($page);
    foreach (['manager_section', 'pay_proposal', 'actual_pay', 'benchmark', 'operational_context', 'storage_path', 'Private'] as $forbidden) {
        expect($encoded)->not->toContain($forbidden);
    }
    expect($page['summary']['final_pay']['amount_minor'])->toBe(5100000)->and($page['summary']['score']['value'])->toBe(100);
    $this->get('/reviews/'.$review->id)->assertOk()->assertSee('View your released outcome');
});

it('records receipt and disagreement separately without rewriting approval or evidence', function (string $choice): void {
    [$hr,$manager,$employee,$review,$submission,$decision] = feedbackFixture();
    $before = $submission->fresh()->getRawOriginal('snapshot');
    $approvedBefore = $decision->fresh()->getRawOriginal('approved_pay');
    feedbackLogin($manager);
    $this->post('/reviews/'.$review->id.'/feedback', feedbackInput($review))->assertSessionHasNoErrors();
    $this->post('/reviews/'.$review->id.'/release', ['expected_version' => $review->refresh()->version])->assertSessionHasNoErrors();
    feedbackLogin($employee);
    $input = ['expected_version' => $review->refresh()->version, 'choice' => $choice, 'comment' => 'My optional employee comment'];
    $this->post('/reviews/'.$review->id.'/acknowledge', $input)->assertSessionHasNoErrors();
    $receipt = PdrAcknowledgement::query()->sole();
    expect($receipt->choice)->toBe($choice)->and($receipt->comment)->toBe('My optional employee comment')->and($review->refresh()->state->value)->toBe('acknowledged')->and($submission->fresh()->getRawOriginal('snapshot'))->toBe($before)->and($decision->fresh()->getRawOriginal('approved_pay'))->toBe($approvedBefore);
    $this->get('/reviews/'.$review->id.'/outcome')->assertOk()->assertSee('Receipt does not mean agreement')->assertSee('My optional employee comment');
    $this->postJson('/reviews/'.$review->id.'/acknowledge', $input)->assertStatus(409);
    expect(PdrAcknowledgement::query()->count())->toBe(1);
    expect(fn () => $receipt->update(['comment' => 'Attempted rewrite']))->toThrow(LogicException::class);
})->with(['receipt', 'disagree']);

it('denies other employees or staff without salary access to the pay-containing released summary', function (): void {
    [$hr,$manager,$employee,$review] = feedbackFixture();
    feedbackLogin($manager);
    $this->post('/reviews/'.$review->id.'/feedback', feedbackInput($review))->assertSessionHasNoErrors();
    $this->post('/reviews/'.$review->id.'/release', ['expected_version' => $review->refresh()->version])->assertSessionHasNoErrors();
    $other = User::factory()->withEmployee()->create();
    foreach ([$other, $hr, $manager] as $actor) {
        feedbackLogin($actor);
        $this->get('/reviews/'.$review->id.'/outcome')->assertForbidden();
        $this->postJson('/reviews/'.$review->id.'/acknowledge', ['expected_version' => $review->refresh()->version, 'choice' => 'receipt'])->assertForbidden();
    }
    $hr->forceFill(['capabilities' => array_merge($hr->capabilities, [Capability::ActualPay->value])])->save();
    feedbackLogin($hr);
    $this->get('/reviews/'.$review->id.'/outcome')->assertOk();
    $hr->forceFill(['capabilities' => [Capability::ActualPay->value]])->save();
    $this->get('/reviews/'.$review->id.'/outcome')->assertForbidden();
});

it('requires current permitted feedback recorder and an explicit HR substitution reason', function (): void {
    [$hr,$manager,$employee,$review] = feedbackFixture();
    feedbackLogin($employee);
    $this->postJson('/reviews/'.$review->id.'/feedback', feedbackInput($review))->assertForbidden();
    feedbackLogin($hr);
    $this->postJson('/reviews/'.$review->id.'/feedback', feedbackInput($review))->assertUnprocessable()->assertJsonValidationErrors('recorder_reason');
    $this->post('/reviews/'.$review->id.'/feedback', feedbackInput($review) + ['recorder_reason' => 'Fictional manager absence: HR records the conversation'])->assertSessionHasNoErrors();
    expect(PdrFeedback::query()->sole()->recorded_by)->toBe($hr->id);
    $employee->employee->forceFill(['manager_id' => null])->save();
    feedbackLogin($manager);
    $this->postJson('/reviews/'.$review->id.'/release', ['expected_version' => $review->refresh()->version])->assertForbidden();
});

it('rejects stale tokens missing feedback and impossible conversation dates without releasing', function (): void {
    [$hr,$manager,$employee,$review] = feedbackFixture();
    feedbackLogin($manager);
    $this->postJson('/reviews/'.$review->id.'/release', ['expected_version' => $review->refresh()->version])->assertStatus(409);
    $input = feedbackInput($review);
    $input['held_on'] = today()->addDay()->toDateString();
    $this->postJson('/reviews/'.$review->id.'/feedback', $input)->assertUnprocessable();
    $input['held_on'] = today()->subDay()->toDateString();
    $this->postJson('/reviews/'.$review->id.'/feedback', $input)->assertUnprocessable();
    $input = feedbackInput($review);
    $input['expected_version'] = 1;
    $this->postJson('/reviews/'.$review->id.'/feedback', $input)->assertStatus(409);
    expect(PdrFeedback::query()->count())->toBe(0)->and(PdrRelease::query()->count())->toBe(0);
});

it('appends pre-release feedback corrections and freezes only the explicitly released revision', function (): void {
    [$hr,$manager,$employee,$review] = feedbackFixture();
    feedbackLogin($manager);
    $this->post('/reviews/'.$review->id.'/feedback', feedbackInput($review))->assertSessionHasNoErrors();
    $first = PdrFeedback::query()->sole();
    $input = feedbackInput($review);
    $input['summary'] = 'Corrected employee-intended feedback';
    $this->post('/reviews/'.$review->id.'/feedback', $input)->assertSessionHasNoErrors();
    expect(PdrFeedback::query()->count())->toBe(2)->and($first->fresh()->summary)->toBe('Employee-intended feedback summary');
    $token = $review->refresh()->version;
    $this->post('/reviews/'.$review->id.'/release', ['expected_version' => $token])->assertSessionHasNoErrors();
    expect(PdrRelease::query()->sole()->summary['feedback']['summary'])->toBe('Corrected employee-intended feedback');
    $this->postJson('/reviews/'.$review->id.'/release', ['expected_version' => $token])->assertStatus(409);
    $this->postJson('/reviews/'.$review->id.'/feedback', feedbackInput($review))->assertStatus(409);
    expect(fn () => PdrRelease::query()->sole()->update(['summary' => []]))->toThrow(LogicException::class);
});
