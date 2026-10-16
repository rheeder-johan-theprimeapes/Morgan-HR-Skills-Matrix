<?php

declare(strict_types=1);

use App\Capability;
use App\Models\Employee;
use App\Models\JobRole;
use App\Models\PayBenchmark;
use App\Models\PayRecord;
use App\Models\User;
use App\Services\PayComparison;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function payInput(): array
{
    return ['amount' => '12345.67', 'basis' => 'annual', 'currency' => 'GBP', 'weekly_hours' => null, 'weeks_per_year' => null, 'effective_on' => '2026-01-01', 'source' => 'Fictional payroll extract', 'expected_sequence' => 0];
}

function benchmarkInput(int $roleId): array
{
    return ['amount' => '25.00', 'basis' => 'hourly', 'currency' => 'GBP', 'weekly_hours' => '37.50', 'weeks_per_year' => '52.00', 'job_role_id' => $roleId, 'title' => 'Illustrative sourced comparator', 'company_context' => 'Fictional engineering company', 'duties' => 'Illustrative comparable production duties', 'limitations' => 'Synthetic demonstration only; not market advice', 'source' => 'Fictional HR-curated survey', 'source_date' => '2026-01-01', 'valid_from' => '2026-01-01', 'valid_to' => '2026-12-31'];
}

function payFixture(): array
{
    $actor = User::factory()->withEmployee()->create(['capabilities' => [Capability::ActualPay->value, Capability::Benchmarks->value]]);
    $role = JobRole::factory()->create();
    $person = Employee::factory()->create(['manager_id' => $actor->employee_id, 'job_role_id' => $role->id]);

    return [$actor, $person, $role];
}

it('records effective pay and immutable corrections while detecting stale writes', function (): void {
    [$hr, $person] = payFixture();
    $input = payInput();
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->post('/employees/'.$person->id.'/pay', $input)->assertRedirect()->assertSessionHasNoErrors();
    $first = PayRecord::query()->sole();
    expect($first->amount_minor)->toBe(1234567)->and($first->recorded_by)->toBe($hr->id)->and($first->sequence)->toBe(1);
    $input['amount'] = '13000.00';
    $input['expected_sequence'] = 1;
    $input['supersedes_id'] = $first->id;
    $input['correction_reason'] = 'Correct fictional source amount';
    $this->post('/employees/'.$person->id.'/pay', $input)->assertSessionHasNoErrors();
    expect($first->fresh()->amount_minor)->toBe(1234567)->and(PayRecord::query()->count())->toBe(2);
    $this->post('/employees/'.$person->id.'/pay', $input)->assertSessionHasErrors('expected_sequence');
    expect(fn () => $first->update(['amount_minor' => 1]))->toThrow(LogicException::class);
    expect(fn () => $first->delete())->toThrow(LogicException::class);
    $this->get('/employees/'.$person->id.'/pay')->assertOk()->assertSee('12,345.67')->assertSee('13,000.00')->assertSee('Correct fictional source amount');
});

it('requires independent actual-pay and benchmark capabilities plus current person scope', function (): void {
    [$hr, $person, $role] = payFixture();
    foreach ([$hr, User::factory()->directoryAdministrator()->accountAdministrator()->create(), User::factory()->withEmployee()->create(['capabilities' => [Capability::ViewOrganisation->value, Capability::ManageFrameworks->value, Capability::AdministerReviews->value]])] as $actor) {
        $this->actingAs($actor)->withSession(['account_session_version' => $actor->session_version]);
        if ($actor->id === $hr->id) {
            $this->get('/employees/'.$person->id.'/pay')->assertOk();
        } else {
            $this->get('/employees/'.$person->id.'/pay')->assertForbidden();
            $this->post('/employees/'.$person->id.'/pay', payInput())->assertForbidden();
            $this->get('/benchmarks')->assertForbidden();
        }
    }
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $other = Employee::factory()->create();
    $this->get('/employees/'.$other->id.'/pay')->assertForbidden();
    $this->post('/employees/'.$other->id.'/pay', payInput())->assertForbidden();
    $person->forceFill(['manager_id' => null])->save();
    $this->get('/employees/'.$person->id.'/pay')->assertForbidden();
});

it('rejects invalid pay terms and forged identities without partial writes', function (string $fault): void {
    [$hr, $person] = payFixture();
    $input = payInput();
    $input = match ($fault) {
        'precision' => array_replace($input, ['amount' => '10.999']), 'negative' => array_replace($input, ['amount' => '-10']), 'basis' => array_replace($input, ['basis' => 'monthly']), 'currency' => array_replace($input, ['currency' => 'XXX']), 'hours' => array_replace($input, ['weekly_hours' => '40']), 'date' => array_replace($input, ['effective_on' => 'not a date']), default => array_replace($input, ['recorded_by' => User::factory()->withEmployee()->create()->id])
    };
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->post('/employees/'.$person->id.'/pay', $input)->assertSessionHasErrors();
    expect(PayRecord::query()->count())->toBe(0);
})->with(['precision', 'negative', 'basis', 'currency', 'hours', 'date', 'identity']);

it('maintains benchmark provenance and preserves stale correction history', function (): void {
    [$hr, $person, $role] = payFixture();
    $input = benchmarkInput($role->id);
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->post('/benchmarks', $input)->assertSessionHasNoErrors();
    $first = PayBenchmark::query()->sole();
    $input['supersedes_id'] = $first->id;
    $input['correction_reason'] = 'Correct fictional survey figure';
    $input['amount'] = '26.00';
    $this->post('/benchmarks', $input)->assertSessionHasNoErrors();
    $this->post('/benchmarks', $input)->assertSessionHasErrors('supersedes_id');
    expect($first->fresh()->amount_minor)->toBe(2500)->and(PayBenchmark::query()->count())->toBe(2);
    $this->get('/benchmarks')->assertOk()->assertSee('Fictional HR-curated survey')->assertSee('Illustrative comparable production duties')->assertSee('Synthetic demonstration only');
    expect(fn () => $first->update(['amount_minor' => 1]))->toThrow(LogicException::class);
});

it('compares explicit annual and hourly terms with source assumptions and no recommendations', function (): void {
    [$hr, $person, $role] = payFixture();
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $input = payInput();
    $input['amount'] = '50000.00';
    $this->post('/employees/'.$person->id.'/pay', $input)->assertSessionHasNoErrors();
    $future = payInput();
    $future['amount'] = '99999.99';
    $future['expected_sequence'] = 1;
    $future['effective_on'] = '2027-01-01';
    $this->post('/employees/'.$person->id.'/pay', $future)->assertSessionHasNoErrors();
    $this->post('/benchmarks', benchmarkInput($role->id))->assertSessionHasNoErrors();
    $benchmark = PayBenchmark::query()->sole();
    $response = $this->get('/employees/'.$person->id.'/pay-comparison?as_of=2026-02-01&benchmark_id='.$benchmark->id)->assertOk()->assertSee('48,750.00')->assertSee('37.50')->assertSee('52.00')->assertSee('No automatic pay recommendation');
    $response->assertViewHas('comparison', fn (array $result): bool => $result['comparable'] && $result['benchmark_minor'] === 4875000 && $result['difference_minor'] === 125000);
    $this->get('/employees/'.$person->id.'/pay-comparison?as_of=2025-12-31&benchmark_id='.$benchmark->id)->assertOk()->assertSee('No effective actual pay');
});

it('refuses missing assumptions different currencies roles or expired benchmark periods', function (string $fault): void {
    [$hr, $person, $role] = payFixture();
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->post('/employees/'.$person->id.'/pay', payInput());
    $input = benchmarkInput($role->id);
    if ($fault === 'hours') {
        $input['weekly_hours'] = null;
        $input['weeks_per_year'] = null;
    }
    if ($fault === 'currency') {
        $input['currency'] = 'USD';
    }
    if ($fault === 'role') {
        $input['job_role_id'] = JobRole::factory()->create()->id;
    }
    if ($fault === 'expired') {
        $input['valid_to'] = '2026-01-15';
    }
    $this->post('/benchmarks', $input)->assertSessionHasNoErrors();
    $benchmark = PayBenchmark::query()->sole();
    $this->get('/employees/'.$person->id.'/pay-comparison?as_of=2026-02-01&benchmark_id='.$benchmark->id)->assertOk()->assertViewHas('comparison', fn (array $result): bool => ! $result['comparable'] && $result['reason'] !== '');
})->with(['hours', 'currency', 'role', 'expired']);

it('uses exact half-up minor-unit conversion rather than guessed working hours', function (): void {
    $comparison = app(PayComparison::class);
    expect($comparison->convert(2500, 'hourly', 'annual', 3750, 5200))->toBe(4875000);
    expect($comparison->convert(4875000, 'annual', 'hourly', 3750, 5200))->toBe(2500);
    expect($comparison->convert(1, 'hourly', 'annual', 100, 150))->toBe(2);
    expect($comparison->convert(10000, 'annual', 'hourly', null, null))->toBeNull();
});

it('requires both independent capabilities for direct comparisons', function (): void {
    foreach ([[Capability::ActualPay->value], [Capability::Benchmarks->value]] as $capabilities) {
        $actor = User::factory()->withEmployee()->create(['capabilities' => $capabilities]);
        $person = Employee::factory()->create(['manager_id' => $actor->employee_id]);
        $this->actingAs($actor)->withSession(['account_session_version' => $actor->session_version]);
        $this->get('/employees/'.$person->id.'/pay-comparison')->assertForbidden();
        if ($capabilities === [Capability::Benchmarks->value]) {
            $this->get('/employees/'.$person->id.'/pay')->assertForbidden();
        } else {
            $this->get('/benchmarks')->assertForbidden();
        }
    }
});

it('rejects another employees correction reference and invalid benchmark provenance', function (): void {
    [$actor, $person, $role] = payFixture();
    $other = PayRecord::factory()->create();
    $this->actingAs($actor)->withSession(['account_session_version' => $actor->session_version]);
    $input = payInput();
    $input['supersedes_id'] = $other->id;
    $input['correction_reason'] = 'Forged correction';
    $this->post('/employees/'.$person->id.'/pay', $input)->assertSessionHasErrors('supersedes_id');
    $benchmark = benchmarkInput($role->id);
    $benchmark['valid_to'] = '2025-01-01';
    $benchmark['limitations'] = '';
    $this->post('/benchmarks', $benchmark)->assertSessionHasErrors(['valid_to', 'limitations']);
    expect(PayRecord::query()->count())->toBe(1)->and(PayBenchmark::query()->count())->toBe(0);
});

it('retains stale pay correction input after following its superseded correction URL', function (): void {
    [$actor, $person] = payFixture();
    $input = payInput();
    $this->actingAs($actor)->withSession(['account_session_version' => $actor->session_version]);
    $this->post('/employees/'.$person->id.'/pay', $input);
    $first = PayRecord::query()->sole();
    $input['expected_sequence'] = 1;
    $input['supersedes_id'] = $first->id;
    $input['correction_reason'] = 'First correction';
    $this->post('/employees/'.$person->id.'/pay', $input);
    $input['source'] = 'Retained stale pay source';
    $url = '/employees/'.$person->id.'/pay?correct='.$first->id;
    $this->from($url)->post('/employees/'.$person->id.'/pay', $input)->assertSessionHasErrors('expected_sequence')->assertRedirect($url);
    $this->get($url)->assertOk()->assertSee('This entry was already corrected')->assertSee('Retained stale pay source')->assertSee('Discard unsaved input and reload');
    expect(PayRecord::query()->count())->toBe(2);
});

it('retains stale benchmark correction input after following its superseded correction URL', function (): void {
    [$actor, $person, $role] = payFixture();
    $input = benchmarkInput($role->id);
    $this->actingAs($actor)->withSession(['account_session_version' => $actor->session_version]);
    $this->post('/benchmarks', $input);
    $first = PayBenchmark::query()->sole();
    $input['supersedes_id'] = $first->id;
    $input['correction_reason'] = 'First correction';
    $this->post('/benchmarks', $input);
    $input['source'] = 'Retained stale benchmark source';
    $url = '/benchmarks?correct='.$first->id;
    $this->from($url)->post('/benchmarks', $input)->assertSessionHasErrors('supersedes_id')->assertRedirect($url);
    $this->get($url)->assertOk()->assertSee('This entry was already corrected')->assertSee('Retained stale benchmark source')->assertSee('Discard unsaved input and reload');
    expect(PayBenchmark::query()->count())->toBe(2);
});
