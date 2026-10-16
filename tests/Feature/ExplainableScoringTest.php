<?php

declare(strict_types=1);

use App\AssessmentKind;
use App\AssessmentState;
use App\Capability;
use App\Models\Assessment;
use App\Models\AssessmentRevision;
use App\Models\Employee;
use App\Models\EvidenceAttachment;
use App\Models\ScoringFramework;
use App\Models\Skill;
use App\Models\SkillLevel;
use App\Models\SkillTarget;
use App\Models\User;
use App\Services\EmployeeScore;
use App\Services\ScoreCalculator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

function scoringDefinition(array $points = [0, 2500, 7500, 10000]): array
{
    $skill = fn (int $id): array => ['skill_id' => $id, 'name' => 'Illustrative skill '.$id, 'allows_na' => true, 'required' => true, 'requires_evidence' => true, 'department_ids' => [], 'job_role_ids' => [], 'levels' => array_map(fn (int $point, int $index): array => ['level_id' => $id * 10 + $index, 'ordinal' => $index, 'label' => 'Level '.$index, 'points' => $point], $points, array_keys($points))];

    return ['components' => [['group_id' => 1, 'name' => 'Production', 'weight' => 1, 'skills' => [$skill(1)]], ['group_id' => 2, 'name' => 'Leadership', 'weight' => 3, 'skills' => [$skill(2)]]], 'bands' => [['label' => 'Developing', 'from' => 0, 'to' => 5000], ['label' => 'Established', 'from' => 5000, 'to' => 10000]]];
}

function scoringInput(int $id, ?int $level, string $state = 'rated', bool $evidence = true): array
{
    return ['skill_id' => $id, 'applicable' => true, 'state' => $state, 'level_id' => $level, 'revision_id' => $id, 'evidence_complete' => $evidence, 'na_reason_present' => true];
}

it('matches the illustrative weighted example with every denominator and source disclosed', function (): void {
    $result = app(ScoreCalculator::class)->calculate(scoringDefinition(), [scoringInput(1, 11), scoringInput(2, 22)]);
    expect($result['score'])->toBe(62.5)->and($result['band'])->toBe('Established')->and($result['complete'])->toBeTrue();
    expect($result['included_weight'])->toBe(4)->and($result['counts']['rated'])->toBe(2);
    expect($result['components'][0]['mean'])->toBe(25.0)->and($result['components'][0]['denominator'])->toBe(1)->and($result['components'][0]['effective_weight'])->toBe(0.25);
    expect($result['components'][1]['mean'])->toBe(75.0)->and($result['components'][1]['effective_weight'])->toBe(0.75);
    expect($result['components'][0]['skills'][0]['revision_id'])->toBe(1);
});

it('keeps real zero included while NA and missing values have different completeness', function (): void {
    $calculator = app(ScoreCalculator::class);
    $config = scoringDefinition();
    $zero = $calculator->calculate($config, [scoringInput(1, 10), scoringInput(2, 23)]);
    expect($zero['score'])->toBe(75.0)->and($zero['counts']['rated'])->toBe(2)->and($zero['complete'])->toBeTrue();
    $na = $calculator->calculate($config, [scoringInput(1, null, 'not_applicable'), scoringInput(2, 23)]);
    expect($na['score'])->toBe(100.0)->and($na['counts']['na'])->toBe(1)->and($na['included_weight'])->toBe(3)->and($na['complete'])->toBeTrue();
    $missing = $calculator->calculate($config, [scoringInput(1, null, 'unassessed'), scoringInput(2, 23)]);
    expect($missing['score'])->toBe(100.0)->and($missing['counts']['missing'])->toBe(1)->and($missing['complete'])->toBeFalse()->and($missing['blocking_reasons'])->not->toBeEmpty();
});

it('blocks all excluded inputs missing evidence invalid references and unsupported NA', function (): void {
    $calculator = app(ScoreCalculator::class);
    $config = scoringDefinition();
    $empty = $calculator->calculate($config, [scoringInput(1, null, 'not_applicable'), scoringInput(2, null, 'not_applicable')]);
    expect($empty['score'])->toBeNull()->and($empty['band'])->toBeNull()->and($empty['complete'])->toBeFalse()->and($empty['included_weight'])->toBe(0);
    $missingEvidence = $calculator->calculate($config, [scoringInput(1, 11, 'rated', false), scoringInput(2, 22)]);
    expect($missingEvidence['complete'])->toBeFalse()->and($missingEvidence['counts']['evidence_missing'])->toBe(1);
    $invalid = $calculator->calculate($config, [scoringInput(1, 999), scoringInput(2, 22)]);
    expect($invalid['complete'])->toBeFalse()->and($invalid['counts']['invalid'])->toBe(1);
    $config['components'][0]['skills'][0]['allows_na'] = false;
    expect($calculator->calculate($config, [scoringInput(1, null, 'not_applicable'), scoringInput(2, 22)])['complete'])->toBeFalse();
});

it('rounds only the final score half up then selects inclusive lower band boundaries', function (): void {
    $config = scoringDefinition([4999, 5000]);
    $config['components'][1]['weight'] = 1;
    $calculator = app(ScoreCalculator::class);
    $result = $calculator->calculate($config, [scoringInput(1, 10), scoringInput(2, 21)]);
    expect($result['score'])->toBe(50.0)->and($result['band'])->toBe('Established');
    expect($result['components'][0]['mean'])->toBe(49.99);
    $below = $calculator->calculate($config, [scoringInput(1, 10), scoringInput(2, 20)]);
    expect($below['score'])->toBe(49.99)->and($below['band'])->toBe('Developing');
    $top = $calculator->calculate(scoringDefinition(), [scoringInput(1, 13), scoringInput(2, 23)]);
    expect($top['score'])->toBe(100.0)->and($top['band'])->toBe('Established');
});

it('renormalises optional exclusions and ignores salary or operational context', function (): void {
    $config = scoringDefinition();
    $config['components'][0]['skills'][0]['required'] = false;
    $inputs = [scoringInput(1, null, 'unassessed'), scoringInput(2, 22)];
    $calculator = app(ScoreCalculator::class);
    $first = $calculator->calculate($config, $inputs);
    $inputs[0]['salary'] = 999999;
    $inputs[1]['rework'] = 99;
    expect($calculator->calculate($config, $inputs))->toBe($first);
    expect($first['score'])->toBe(75.0)->and($first['complete'])->toBeTrue()->and($first['components'][0]['effective_weight'])->toBe(0.0);
});

it('rejects duplicate scalar inputs rather than selecting an arbitrary rating', function (): void {
    expect(fn () => app(ScoreCalculator::class)->calculate(scoringDefinition(), [scoringInput(1, 11), scoringInput(1, 13)]))->toThrow(InvalidArgumentException::class);
});

function employeeScoringFixture(): array
{
    $manager = User::factory()->withEmployee()->create();
    $person = Employee::factory()->create(['manager_id' => $manager->employee_id]);
    $skill = Skill::factory()->create();
    $level = SkillLevel::factory()->for($skill)->create(['ordinal' => 0]);
    $top = SkillLevel::factory()->for($skill)->create(['ordinal' => 1]);
    $config = scoringDefinition([0, 10000]);
    $config['components'] = [$config['components'][0]];
    $config['components'][0]['skills'][0]['skill_id'] = $skill->id;
    $config['components'][0]['skills'][0]['levels'][0]['level_id'] = $level->id;
    $config['components'][0]['skills'][0]['levels'][1]['level_id'] = $top->id;
    $framework = ScoringFramework::factory()->create(['definition' => $config, 'published_at' => now()]);
    $assessment = Assessment::factory()->create(['employee_id' => $person->id, 'skill_id' => $skill->id, 'kind' => AssessmentKind::Manager]);
    $revision = AssessmentRevision::factory()->create(['assessment_id' => $assessment->id, 'actor_id' => $manager->id, 'state' => AssessmentState::Rated, 'skill_level_id' => $level->id, 'evidence_notes' => 'Fictional dated observation', 'comment' => 'Private manager detail never in scoring payload']);
    $assessment->update(['current_revision_id' => $revision->id, 'revision' => 1]);

    return [$manager, $person, $framework, $skill, $top];
}

it('shows a scoped manager preview with version and safe breakdown but no internal comments', function (): void {
    [$manager, $person, $framework] = employeeScoringFixture();
    $this->actingAs($manager)->withSession(['account_session_version' => $manager->session_version]);
    $this->get('/employees/'.$person->id.'/scores')->assertOk()->assertSee($framework->name);
    $this->get('/employees/'.$person->id.'/scores/'.$framework->id)->assertOk()->assertSee('0.00')->assertSee('Illustrative')->assertSee('Provisional')->assertDontSee('Private manager detail');
    $unrelated = Employee::factory()->create();
    $this->get('/employees/'.$unrelated->id.'/scores/'.$framework->id)->assertForbidden();
    $person->forceFill(['manager_id' => null])->save();
    $this->get('/employees/'.$person->id.'/scores/'.$framework->id)->assertForbidden();
});

it('does not reveal raw manager scoring to employees or organisation readers', function (): void {
    [$manager, $person, $framework] = employeeScoringFixture();
    $employee = User::factory()->create(['employee_id' => $person->id]);
    foreach ([$employee, User::factory()->withEmployee()->create(['capabilities' => [Capability::ViewOrganisation->value]])] as $actor) {
        $this->actingAs($actor)->withSession(['account_session_version' => $actor->session_version]);
        $this->get('/employees/'.$person->id.'/scores/'.$framework->id)->assertForbidden();
    }
    $framework->newQuery()->whereKey($framework->id)->update(['published_at' => null]);
    $this->actingAs($manager)->withSession(['account_session_version' => $manager->session_version]);
    $this->get('/employees/'.$person->id.'/scores/'.$framework->id)->assertNotFound();
});

it('uses published applicability and individual exceptions without rewriting the framework', function (): void {
    [$manager, $person, $framework, $skill, $top] = employeeScoringFixture();
    $config = $framework->definition;
    $config['components'][0]['skills'][0]['department_ids'] = [999999];
    $framework->newQuery()->whereKey($framework->id)->update(['definition' => $config]);
    $this->actingAs($manager)->withSession(['account_session_version' => $manager->session_version]);
    $this->get('/employees/'.$person->id.'/scores/'.$framework->id)->assertOk()->assertSee('No score')->assertSee('Outside published applicability');
    SkillTarget::factory()->create(['employee_id' => $person->id, 'department_id' => null, 'job_role_id' => null, 'skill_id' => $skill->id, 'skill_level_id' => $top->id, 'reason' => 'Illustrative approved individual exception']);
    $this->get('/employees/'.$person->id.'/scores/'.$framework->id)->assertOk()->assertSee('0.00')->assertSee('Individual target exception');
    $skill->update(['is_active' => false]);
    $this->get('/employees/'.$person->id.'/scores/'.$framework->id)->assertOk()->assertSee('0.00');
});

it('keeps invalidated active individual exceptions applicable and blocking for HR attention', function (): void {
    [$manager, $person, $framework, $skill, $top] = employeeScoringFixture();
    $config = $framework->definition;
    $config['components'][0]['skills'][0]['department_ids'] = [999999];
    $otherSkill = Skill::factory()->create();
    $otherLevel = SkillLevel::factory()->for($otherSkill)->create(['ordinal' => 0]);
    $otherConfig = $config['components'][0]['skills'][0];
    $otherConfig['skill_id'] = $otherSkill->id;
    $otherConfig['department_ids'] = [];
    $otherConfig['levels'][0]['level_id'] = $otherLevel->id;
    $config['components'][0]['skills'][] = $otherConfig;
    $head = Assessment::factory()->create(['employee_id' => $person->id, 'skill_id' => $otherSkill->id, 'kind' => AssessmentKind::Manager]);
    $revision = AssessmentRevision::factory()->create(['assessment_id' => $head->id, 'state' => AssessmentState::Rated, 'skill_level_id' => $otherLevel->id, 'evidence_notes' => 'Fictional sufficient observation']);
    $head->update(['current_revision_id' => $revision->id, 'revision' => 1]);
    $framework->newQuery()->whereKey($framework->id)->update(['definition' => $config]);
    SkillTarget::factory()->create(['employee_id' => $person->id, 'department_id' => null, 'job_role_id' => null, 'skill_id' => $skill->id, 'skill_level_id' => $top->id, 'reason' => 'Illustrative target exception']);
    $top->update(['is_active' => false]);
    $result = app(EmployeeScore::class)->preview($manager, $person, $framework);
    expect($result['complete'])->toBeFalse()->and($result['counts']['inapplicable'])->toBe(0);
    expect(implode(' ', $result['blocking_reasons']))->toContain('HR attention');
});

it('requires available intact attachment content when notes do not satisfy evidence', function (string $fileState): void {
    Storage::fake('evidence');
    [$manager, $person, $framework, $skill] = employeeScoringFixture();
    $head = Assessment::query()->where('employee_id', $person->id)->sole();
    $revision = AssessmentRevision::factory()->create(['assessment_id' => $head->id, 'revision' => 2, 'actor_id' => $manager->id, 'state' => AssessmentState::Rated, 'skill_level_id' => $head->currentRevision->skill_level_id, 'evidence_notes' => null]);
    $head->update(['revision' => 2, 'current_revision_id' => $revision->id]);
    $original = 'Fictional evidence contents';
    EvidenceAttachment::factory()->create(['assessment_revision_id' => $revision->id, 'uploaded_by' => $manager->id, 'storage_path' => 'fictional-evidence.pdf', 'byte_size' => strlen($original), 'sha256' => hash('sha256', $original)]);
    if ($fileState !== 'missing') {
        Storage::disk('evidence')->put('fictional-evidence.pdf', $fileState === 'valid' ? $original : 'Changed fictional contents');
    }
    $result = app(EmployeeScore::class)->preview($manager, $person, $framework);
    expect($result['complete'])->toBe($fileState === 'valid');
    expect($result['counts']['evidence_missing'])->toBe($fileState === 'valid' ? 0 : 1);
})->with(['valid', 'missing', 'changed']);
