<?php

declare(strict_types=1);
use App\Actions\ReviewPdrSubmission;
use App\Actions\SubmitPdrReview;
use App\Capability;
use App\Models\PayRecord;
use App\Models\PdrDecision;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

require_once __DIR__.'/../Support/PdrFixtures.php';
uses(LazilyRefreshDatabase::class);
function sltFixture(bool $hrReviewed = true): array
{
    [$hr,$manager,$employee,$cycle,$review] = submissionFixture();
    PayRecord::factory()->create(['employee_id' => $employee->employee_id, 'amount_minor' => 5000000, 'source' => 'Fictional current pay']);
    $submission = app(SubmitPdrReview::class)->handle($manager, $review, submissionTokens($review));
    if ($hrReviewed) {
        app(ReviewPdrSubmission::class)->handle($hr, $review, $submission, ['expected_version' => $review->refresh()->version, 'action' => 'review', 'reason' => 'Fictional completeness check']);
    }
    $slt = User::factory()->withEmployee()->create(['capabilities' => [Capability::DecideReviews->value, Capability::ApprovePay->value, Capability::ActualPay->value, Capability::ViewOrganisation->value]]);

    return [$hr, $manager, $employee, $review, $submission, $slt];
}
function sltSignIn(User $user): void
{
    test()->actingAs($user)->withSession(['account_session_version' => $user->session_version]);
}
function sltDecisionInput($review, string $action = 'approve'): array
{
    return ['expected_version' => $review->refresh()->version, 'action' => $action, 'reason' => 'Private human decision rationale', 'pay_outcome' => 'change', 'amount' => '51000.00', 'currency' => 'GBP', 'basis' => 'annual', 'effective_on' => '2027-01-01'];
}

it('lets an authorised scoped decision maker compare and approve one exact reviewed revision without changing actual pay', function (): void {
    [$hr,$manager,$employee,$review,$submission,$slt] = sltFixture();
    sltSignIn($slt);
    $this->get('/review-decisions')->assertOk()->assertSee($employee->employee->name)->assertSee('50000.00')->assertSee('100.00');
    $this->get('/reviews/'.$review->id.'/submissions/'.$submission->id)->assertOk()->assertSee('SLT decision');
    $this->get('/reviews/'.$review->id)->assertRedirect('/reviews/'.$review->id.'/submissions/'.$submission->id);
    $input = sltDecisionInput($review);
    $url = '/reviews/'.$review->id.'/submissions/'.$submission->id.'/decision';
    $this->post($url, $input)->assertSessionHasNoErrors()->assertRedirect();
    $decision = PdrDecision::query()->sole();
    expect($decision->pdr_submission_id)->toBe($submission->id)->and($decision->approved_pay['amount_minor'])->toBe(5100000)->and($review->refresh()->state->value)->toBe('approved');
    expect(PayRecord::query()->count())->toBe(1)->and(PayRecord::query()->sole()->amount_minor)->toBe(5000000);
    $this->get('/reviews/'.$review->id.'/submissions/'.$submission->id)->assertOk()->assertSee('Approved human pay outcome')->assertSee('51000.00')->assertSee('2027-01-01');
    $this->postJson($url, $input)->assertStatus(409);
    expect(PdrDecision::query()->count())->toBe(1);
    expect(fn () => $decision->update(['reason' => 'Rewrite']))->toThrow(LogicException::class);
    sltSignIn($manager);
    $this->get('/reviews/'.$review->id)->assertOk()->assertDontSee('Private human decision rationale');
    sltSignIn($hr);
    $this->get('/reviews/'.$review->id.'/submissions/'.$submission->id)->assertOk()->assertDontSee('Private human decision rationale')->assertDontSee('51000.00');
    sltSignIn($employee);
    $this->get('/reviews/'.$review->id)->assertOk()->assertDontSee('Private human decision rationale')->assertDontSee('51000');
});

it('requires every independent decision and salary grant plus current person scope', function (string $missing): void {
    [$hr,$manager,$employee,$review,$submission,$slt] = sltFixture();
    $caps = $slt->capabilities;
    $caps = array_values(array_diff($caps, [$missing]));
    $slt->forceFill(['capabilities' => $caps])->save();
    sltSignIn($slt);
    $this->get('/review-decisions')->assertForbidden();
    $this->postJson('/reviews/'.$review->id.'/submissions/'.$submission->id.'/decision', sltDecisionInput($review))->assertForbidden();
    expect(PdrDecision::query()->count())->toBe(0);
})->with([Capability::DecideReviews->value, Capability::ApprovePay->value, Capability::ActualPay->value]);

it('denies unrelated decision makers subjects submitting managers and ordinary review administrators', function (): void {
    [$hr,$manager,$employee,$review,$submission,$slt] = sltFixture();
    $all = [Capability::DecideReviews->value, Capability::ApprovePay->value, Capability::ActualPay->value, Capability::ViewOrganisation->value];
    $employee->forceFill(['capabilities' => $all])->save();
    $manager->forceFill(['capabilities' => $all])->save();
    $unrelated = User::factory()->withEmployee()->create(['capabilities' => array_values(array_diff($all, [Capability::ViewOrganisation->value]))]);
    foreach ([$hr, $manager, $employee, $unrelated] as $actor) {
        sltSignIn($actor);
        $this->postJson('/reviews/'.$review->id.'/submissions/'.$submission->id.'/decision', sltDecisionInput($review))->assertForbidden();
    }
    expect(PdrDecision::query()->count())->toBe(0);
});

it('rejects skipped HR stale versions and malformed approved terms atomically', function (): void {
    [$hr,$manager,$employee,$review,$submission,$slt] = sltFixture(false);
    sltSignIn($slt);
    $url = '/reviews/'.$review->id.'/submissions/'.$submission->id.'/decision';
    $this->get('/reviews/'.$review->id.'/submissions/'.$submission->id)->assertForbidden();
    $this->get('/reviews/'.$review->id)->assertForbidden();
    $this->postJson($url, sltDecisionInput($review))->assertStatus(409);
    app(ReviewPdrSubmission::class)->handle($hr, $review, $submission, ['expected_version' => $review->refresh()->version, 'action' => 'review', 'reason' => 'Checked']);
    $input = sltDecisionInput($review);
    $input['expected_version'] = 1;
    $this->postJson($url, $input)->assertStatus(409);
    $input = sltDecisionInput($review);
    $input['currency'] = '';
    $this->postJson($url, $input)->assertUnprocessable();
    $input = sltDecisionInput($review);
    $input['amount'] = '-1';
    $this->postJson($url, $input)->assertUnprocessable();
    expect(PdrDecision::query()->count())->toBe(0)->and($review->refresh()->state->value)->toBe('hr_reviewed');
});

it('returns an immutable reviewed revision to a distinct draft with the human reason', function (): void {
    [$hr,$manager,$employee,$review,$submission,$slt] = sltFixture();
    sltSignIn($slt);
    $oldDraft = $review->current_draft_id;
    $input = ['expected_version' => $review->refresh()->version, 'action' => 'return', 'reason' => 'Clarify the fictional proposed terms'];
    $this->post('/reviews/'.$review->id.'/submissions/'.$submission->id.'/decision', $input)->assertSessionHasNoErrors();
    expect($review->refresh()->state->value)->toBe('draft')->and($review->current_draft_id)->not->toBe($oldDraft)->and($review->currentDraft->previous_submission_id)->toBe($submission->id)->and(PdrDecision::query()->sole()->approved_pay)->toBeNull();
    sltSignIn($manager);
    $this->get('/reviews/'.$review->id)->assertOk()->assertSee('Clarify the fictional proposed terms');
});

it('records an explicit no change decision without guessed salary terms', function (): void {
    [$hr,$manager,$employee,$review,$submission,$slt] = sltFixture();
    sltSignIn($slt);
    $input = ['expected_version' => $review->refresh()->version, 'action' => 'approve', 'reason' => 'Fictional human no-change decision', 'pay_outcome' => 'no_change'];
    $this->post('/reviews/'.$review->id.'/submissions/'.$submission->id.'/decision', $input)->assertSessionHasNoErrors();
    expect(PdrDecision::query()->sole()->approved_pay)->toBe(['outcome' => 'no_change'])->and(PayRecord::query()->count())->toBe(1);
});
