<?php

declare(strict_types=1);

use App\AssessmentKind;
use App\AssessmentState;
use App\Capability;
use App\Models\Assessment;
use App\Models\AssessmentRevision;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Skill;
use App\Models\SkillLevel;
use App\Models\SkillTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

function matrixAssessment(User $author, Employee $person, Skill $skill, AssessmentKind $kind, AssessmentState $state, ?int $ordinal): Assessment
{
    $head = Assessment::factory()->create(['employee_id' => $person->id, 'skill_id' => $skill->id, 'kind' => $kind]);
    $revision = AssessmentRevision::factory()->create(['assessment_id' => $head->id, 'actor_id' => $author->id, 'state' => $state, 'snapshot' => ['skill_name' => $skill->name, 'ordinal' => $ordinal], 'comment' => 'PrivateMatrixComment', 'evidence_notes' => 'PrivateMatrixEvidence']);
    $head->forceFill(['revision' => 1, 'current_revision_id' => $revision->id])->save();

    return $head;
}

it('requires authentication for the matrix', function (): void {
    $this->get('/skills-matrix')->assertRedirect('/login');
});

it('limits employees to their own permitted rating cells', function (): void {
    $account = User::factory()->withEmployee()->create();
    $outside = Employee::factory()->create(['name' => 'OutsideMatrixScope']);
    $skill = Skill::factory()->create();
    matrixAssessment($account, $account->employee, $skill, AssessmentKind::Self, AssessmentState::Rated, 0);
    matrixAssessment($account, $account->employee, $skill, AssessmentKind::Manager, AssessmentState::Rated, 3);
    $this->actingAs($account)->withSession(['account_session_version' => $account->session_version]);
    $response = $this->get('/skills-matrix')->assertOk()->assertSee($account->employee->name)->assertDontSee($outside->name)->assertDontSee('PrivateMatrixComment')->assertDontSee('PrivateMatrixEvidence');
    $person = $response->viewData('people')->items()[0];
    expect($person['cells'][0]['self']['ordinal'])->toBe(0);
    expect($person['cells'][0]['manager']['state'])->toBe('restricted');
    expect($person['cells'][0]['manager']['ordinal'])->toBeNull();
});

it('shows direct and indirect reports while rejecting a department-based scope expansion', function (): void {
    $manager = User::factory()->withEmployee()->create();
    $department = Department::factory()->create();
    $direct = Employee::factory()->create(['manager_id' => $manager->employee_id, 'department_id' => $department->id]);
    $indirect = Employee::factory()->create(['manager_id' => $direct->id, 'department_id' => $department->id]);
    $outside = Employee::factory()->create(['department_id' => $department->id, 'name' => 'OutsideDepartmentMarker']);
    Skill::factory()->create();
    $this->actingAs($manager)->withSession(['account_session_version' => $manager->session_version]);
    $this->get('/skills-matrix?department_id='.$department->id)->assertOk()->assertSee($direct->name)->assertSee($indirect->name)->assertDontSee($outside->name);
    $response = $this->get('/skills-matrix?employee_id='.$outside->id)->assertOk()->assertDontSee($outside->name);
    expect($response->viewData('people')->total())->toBe(0);
});

it('uses resolved target provenance and manager gaps without averaging ratings', function (): void {
    $manager = User::factory()->withEmployee()->create();
    $person = Employee::factory()->create(['manager_id' => $manager->employee_id]);
    $skill = Skill::factory()->create();
    $targetLevel = SkillLevel::factory()->create(['skill_id' => $skill->id, 'ordinal' => 4]);
    SkillTarget::factory()->create(['employee_id' => $person->id, 'skill_id' => $skill->id, 'skill_level_id' => $targetLevel->id, 'reason' => 'Illustrative personal target']);
    matrixAssessment($manager, $person, $skill, AssessmentKind::Self, AssessmentState::Rated, 4);
    matrixAssessment($manager, $person, $skill, AssessmentKind::Manager, AssessmentState::Rated, 1);
    $this->actingAs($manager)->withSession(['account_session_version' => $manager->session_version]);
    $response = $this->get('/skills-matrix?employee_id='.$person->id)->assertOk()->assertSee('HR-approved individual exception');
    $cell = $response->viewData('people')->items()[0]['cells'][0];
    expect($cell['self_gap'])->toBe(0);
    expect($cell['manager_gap'])->toBe(3);
    expect($cell['target']['ordinal'])->toBe(4);
    expect($response->viewData('coverage'))->toMatchArray(['manager_rated' => 1, 'manager_eligible' => 1, 'self_rated' => 1, 'self_eligible' => 1]);
});

it('distinguishes not applicable N/A unassessed zero and missing targets in text', function (): void {
    $account = User::factory()->withEmployee()->create();
    $na = Skill::factory()->create(['name' => 'A NA skill', 'allows_na' => true]);
    $zero = Skill::factory()->create(['name' => 'B Zero skill']);
    Skill::factory()->create(['name' => 'C Missing skill']);
    $excluded = Skill::factory()->create(['name' => 'D Outside duty']);
    $excluded->departments()->attach(Department::factory()->create());
    matrixAssessment($account, $account->employee, $na, AssessmentKind::Self, AssessmentState::NotApplicable, null);
    matrixAssessment($account, $account->employee, $zero, AssessmentKind::Self, AssessmentState::Rated, 0);
    $this->actingAs($account)->withSession(['account_session_version' => $account->session_version]);
    $this->get('/skills-matrix')->assertOk()->assertSee('Not applicable to duties')->assertSee('Not applicable (N/A)')->assertSee('Unassessed')->assertSee('Assessed level 0')->assertSee('No target')->assertSee('Restricted');
});

it('does not make raw assessments visible through organisation-reading authority', function (): void {
    $reader = User::factory()->withEmployee()->create(['capabilities' => [Capability::ViewOrganisation->value]]);
    $outside = Employee::factory()->create();
    $skill = Skill::factory()->create();
    matrixAssessment($reader, $outside, $skill, AssessmentKind::Self, AssessmentState::Rated, 3);
    matrixAssessment($reader, $outside, $skill, AssessmentKind::Manager, AssessmentState::Rated, 4);
    $this->actingAs($reader)->withSession(['account_session_version' => $reader->session_version]);
    $response = $this->get('/skills-matrix?employee_id='.$outside->id)->assertOk();
    $cell = $response->viewData('people')->items()[0]['cells'][0];
    expect($cell['self']['state'])->toBe('restricted');
    expect($cell['manager']['state'])->toBe('restricted');
    expect(json_encode($response->viewData('people')->items()))->not->toContain('PrivateMatrixComment', 'PrivateMatrixEvidence');
    expect($response->viewData('coverage')['manager_eligible'])->toBe(0);
});

it('preserves filters in both people and skill pagination links', function (): void {
    $hr = User::factory()->withEmployee()->create(['capabilities' => [Capability::ViewOrganisation->value, Capability::AdministerReviews->value]]);
    $department = Department::factory()->create();
    Employee::factory()->count(27)->create(['department_id' => $department->id]);
    Skill::factory()->count(14)->create();
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $response = $this->get('/skills-matrix?department_id='.$department->id.'&status=active')->assertOk();
    parse_str(parse_url($response->viewData('people')->nextPageUrl(), PHP_URL_QUERY), $peopleParameters);
    parse_str(parse_url($response->viewData('skills')->nextPageUrl(), PHP_URL_QUERY), $skillParameters);
    expect($peopleParameters)->toMatchArray(['department_id' => (string) $department->id, 'page' => '2', 'status' => 'active']);
    expect($skillParameters)->toMatchArray(['department_id' => (string) $department->id, 'skill_page' => '2', 'status' => 'active']);
});

it('rejects malformed filters rather than passing arbitrary identifiers to queries', function (): void {
    $account = User::factory()->withEmployee()->create();
    $this->actingAs($account)->withSession(['account_session_version' => $account->session_version]);
    $this->getJson('/skills-matrix?department_id[]=1&sort=password')->assertUnprocessable()->assertJsonValidationErrors(['department_id', 'sort']);
});

it('loads a growing matrix in bounded queries instead of querying each cell', function (): void {
    $manager = User::factory()->withEmployee()->create();
    Employee::factory()->create(['manager_id' => $manager->employee_id]);
    Skill::factory()->create();
    $this->actingAs($manager)->withSession(['account_session_version' => $manager->session_version]);
    DB::enableQueryLog();
    $this->get('/skills-matrix')->assertOk();
    $smallCount = count(DB::getQueryLog());
    DB::flushQueryLog();
    DB::disableQueryLog();
    Employee::factory()->count(23)->create(['manager_id' => $manager->employee_id]);
    Skill::factory()->count(11)->create();
    DB::enableQueryLog();
    $response = $this->get('/skills-matrix')->assertOk();
    $largeCount = count(DB::getQueryLog());
    DB::disableQueryLog();
    expect($response->viewData('people')->count())->toBe(25);
    expect($largeCount)->toBeLessThanOrEqual($smallCount + 4);
    expect($largeCount)->toBeLessThanOrEqual(40);
});

it('refuses preview preparation on the ordinary test connection before migrating or seeding', function (): void {
    $person = Employee::factory()->create();
    $this->artisan('app:prepare-skills-preview')->assertExitCode(1);
    $this->assertModelExists($person);
    expect(Employee::query()->count())->toBe(1);
});
