<?php

declare(strict_types=1);

use App\Actions\SaveOperationalRecord;
use App\AssessmentKind;
use App\AssessmentState;
use App\Capability;
use App\Models\Assessment;
use App\Models\AssessmentRevision;
use App\Models\Employee;
use App\Models\OperationalRecord;
use App\Models\ScoringFramework;
use App\Models\Skill;
use App\Models\SkillLevel;
use App\Models\User;
use App\Services\EmployeeScore;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function operationalInput(): array
{
    return ['metric' => 'absence_hours', 'unit' => 'hours', 'value' => '3.50', 'period_start' => '2026-01-01', 'period_end' => '2026-01-31', 'source' => 'Fictional source register', 'notes' => 'Fictional observation; not a real attendance record', 'expected_sequence' => 0];
}

function operationalFixture(): array
{
    $actor = User::factory()->withEmployee()->create(['capabilities' => [Capability::SensitiveContext->value]]);
    $person = Employee::factory()->create(['manager_id' => $actor->employee_id]);

    return [$actor, $person];
}

it('records source period units and actor while preserving corrections and stale protection', function (): void {
    [$actor, $person] = operationalFixture();
    $input = operationalInput();
    $this->actingAs($actor)->withSession(['account_session_version' => $actor->session_version]);
    $this->post('/employees/'.$person->id.'/operational-context', $input)->assertRedirect()->assertSessionHasNoErrors();
    $first = OperationalRecord::query()->sole();
    expect($first->value_hundredths)->toBe(350)->and($first->recorded_by)->toBe($actor->id)->and($first->is_interpretation)->toBeFalse();
    $input['expected_sequence'] = 1;
    $input['supersedes_id'] = $first->id;
    $input['correction_reason'] = 'Correct fictional source total';
    $input['value'] = '2.00';
    $this->post('/employees/'.$person->id.'/operational-context', $input)->assertSessionHasNoErrors();
    $this->post('/employees/'.$person->id.'/operational-context', $input)->assertSessionHasErrors('expected_sequence');
    expect($first->fresh()->value_hundredths)->toBe(350)->and(OperationalRecord::query()->count())->toBe(2);
    expect(fn () => $first->update(['value_hundredths' => 0]))->toThrow(LogicException::class);
    expect(fn () => $first->delete())->toThrow(LogicException::class);
    $this->get('/employees/'.$person->id.'/operational-context')->assertOk()->assertSee('Fictional source register')->assertSee('Correct fictional source total')->assertSee('Superseded');
});

it('requires separate sensitive-context capability and current person scope for every request', function (): void {
    [$actor, $person] = operationalFixture();
    $ordinaryManager = User::factory()->withEmployee()->create();
    $report = Employee::factory()->create(['manager_id' => $ordinaryManager->employee_id]);
    foreach ([$ordinaryManager, User::factory()->directoryAdministrator()->accountAdministrator()->create(), User::factory()->withEmployee()->create(['capabilities' => [Capability::ViewOrganisation->value, Capability::ActualPay->value, Capability::AdministerReviews->value]])] as $forbidden) {
        $this->actingAs($forbidden)->withSession(['account_session_version' => $forbidden->session_version]);
        $this->get('/employees/'.$report->id.'/operational-context')->assertForbidden();
        $this->post('/employees/'.$report->id.'/operational-context', operationalInput())->assertForbidden();
    }
    $this->actingAs($actor)->withSession(['account_session_version' => $actor->session_version]);
    $this->get('/employees/'.$report->id.'/operational-context')->assertForbidden();
    $person->forceFill(['manager_id' => null])->save();
    $this->get('/employees/'.$person->id.'/operational-context')->assertForbidden();
    expect(OperationalRecord::query()->count())->toBe(0);
});

it('rejects incompatible units missing periods invalid values and forged identities', function (string $fault): void {
    [$actor, $person] = operationalFixture();
    $input = operationalInput();
    if ($fault === 'unit') {
        $input['unit'] = 'percent';
    }
    if ($fault === 'period') {
        $input['period_end'] = null;
    }
    if ($fault === 'reversed') {
        $input['period_end'] = '2025-12-31';
    }
    if ($fault === 'negative') {
        $input['value'] = '-1';
    }
    if ($fault === 'percent') {
        $input['metric'] = 'right_first_time';
        $input['unit'] = 'percent';
        $input['value'] = '100.01';
    }
    if ($fault === 'count') {
        $input['metric'] = 'ppe_observations';
        $input['unit'] = 'observations';
        $input['value'] = '1.25';
    }
    if ($fault === 'identity') {
        $input['employee_id'] = Employee::factory()->create()->id;
    }
    $this->actingAs($actor)->withSession(['account_session_version' => $actor->session_version]);
    $this->post('/employees/'.$person->id.'/operational-context', $input)->assertSessionHasErrors();
    expect(OperationalRecord::query()->count())->toBe(0);
})->with(['unit', 'period', 'reversed', 'negative', 'percent', 'count', 'identity']);

it('labels human interpretation separately and requires substantive narrative', function (): void {
    [$actor, $person] = operationalFixture();
    $input = operationalInput();
    $input['metric'] = 'retention_context';
    $input['unit'] = 'narrative';
    $input['value'] = null;
    $input['notes'] = null;
    $this->actingAs($actor)->withSession(['account_session_version' => $actor->session_version]);
    $this->post('/employees/'.$person->id.'/operational-context', $input)->assertSessionHasErrors('notes');
    $input['notes'] = 'Fictional HR interpretation with dated source; no automatic penalty';
    $this->post('/employees/'.$person->id.'/operational-context', $input)->assertSessionHasNoErrors();
    expect(OperationalRecord::query()->sole()->is_interpretation)->toBeTrue();
    $this->get('/employees/'.$person->id.'/operational-context')->assertOk()->assertSee('Human interpretation')->assertSee('no automatic penalty');
});

it('flags current overlapping source periods and explicitly shows missing evidence', function (): void {
    [$actor, $person] = operationalFixture();
    $input = operationalInput();
    $this->actingAs($actor)->withSession(['account_session_version' => $actor->session_version]);
    $this->post('/employees/'.$person->id.'/operational-context', $input);
    $input['expected_sequence'] = 1;
    $input['period_start'] = '2026-01-15';
    $input['period_end'] = '2026-02-15';
    $input['value'] = '1.00';
    $this->post('/employees/'.$person->id.'/operational-context', $input)->assertSessionHasNoErrors();
    $this->get('/employees/'.$person->id.'/operational-context?from=2026-01-01&to=2026-01-31')->assertOk()->assertSee('Overlapping source period')->assertSee('Full source period extends outside this filter')->assertSee('not added together');
    $this->get('/employees/'.$person->id.'/operational-context?from=2025-01-01&to=2025-01-31')->assertOk()->assertSee('No evidence recorded for this period')->assertSee('does not mean zero');
    $this->get('/employees/'.$person->id.'/operational-context?from=2026-02-01&to=2026-01-01')->assertSessionHasErrors('to');
});

it('rejects correction references belonging to another employee', function (): void {
    [$actor, $person] = operationalFixture();
    $other = OperationalRecord::factory()->create();
    $input = operationalInput();
    $input['supersedes_id'] = $other->id;
    $input['correction_reason'] = 'Forged correction';
    $this->actingAs($actor)->withSession(['account_session_version' => $actor->session_version]);
    $this->post('/employees/'.$person->id.'/operational-context', $input)->assertSessionHasErrors('supersedes_id');
    expect(OperationalRecord::query()->count())->toBe(1);
});

it('keeps operational changes outside the current manager skills calculation', function (): void {
    [$actor, $person] = operationalFixture();
    $skill = Skill::factory()->create();
    $zero = SkillLevel::factory()->for($skill)->create(['ordinal' => 0]);
    $top = SkillLevel::factory()->for($skill)->create(['ordinal' => 1]);
    $framework = ScoringFramework::factory()->create(['published_at' => now(), 'definition' => ['components' => [['group_id' => $skill->skill_group_id, 'name' => 'Illustrative group', 'weight' => 1, 'skills' => [['skill_id' => $skill->id, 'name' => 'Illustrative skill', 'allows_na' => false, 'required' => true, 'requires_evidence' => false, 'department_ids' => [], 'job_role_ids' => [], 'levels' => [['level_id' => $zero->id, 'ordinal' => 0, 'label' => 'Zero', 'points' => 0], ['level_id' => $top->id, 'ordinal' => 1, 'label' => 'Established', 'points' => 7500]]]]]], 'bands' => [['label' => 'Illustrative band', 'from' => 0, 'to' => 10000]]]]);
    $head = Assessment::factory()->create(['employee_id' => $person->id, 'skill_id' => $skill->id, 'kind' => AssessmentKind::Manager]);
    $revision = AssessmentRevision::factory()->create(['assessment_id' => $head->id, 'state' => AssessmentState::Rated, 'skill_level_id' => $top->id]);
    $head->update(['current_revision_id' => $revision->id, 'revision' => 1]);
    $scores = app(EmployeeScore::class);
    $before = $scores->preview($actor, $person, $framework);
    app(SaveOperationalRecord::class)->handle($actor, $person, operationalInput());
    expect($scores->preview($actor, $person, $framework))->toBe($before);
    expect($before['score'])->toBe(75.0);
});

it('retains stale context correction input after following its superseded correction URL', function (): void {
    [$actor, $person] = operationalFixture();
    $input = operationalInput();
    $this->actingAs($actor)->withSession(['account_session_version' => $actor->session_version]);
    $this->post('/employees/'.$person->id.'/operational-context', $input);
    $first = OperationalRecord::query()->sole();
    $input['expected_sequence'] = 1;
    $input['supersedes_id'] = $first->id;
    $input['correction_reason'] = 'First correction';
    $this->post('/employees/'.$person->id.'/operational-context', $input);
    $input['notes'] = 'Retained stale context narrative';
    $url = '/employees/'.$person->id.'/operational-context?correct='.$first->id;
    $this->from($url)->post('/employees/'.$person->id.'/operational-context', $input)->assertSessionHasErrors('expected_sequence')->assertRedirect($url);
    $this->get($url)->assertOk()->assertSee('This entry was already corrected')->assertSee('Retained stale context narrative')->assertSee('Show all periods / discard unsaved input');
    expect(OperationalRecord::query()->count())->toBe(2);
});
