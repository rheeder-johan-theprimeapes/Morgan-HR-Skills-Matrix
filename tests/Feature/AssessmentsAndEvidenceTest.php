<?php

declare(strict_types=1);

use App\Actions\SaveAssessment;
use App\Models\Assessment;
use App\Models\AssessmentRevision;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EvidenceAttachment;
use App\Models\Skill;
use App\Models\SkillLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('evidence');
});

it('shows an employee their own applicable self-assessment form', function (): void {
    $account = User::factory()->withEmployee()->create();
    $level = SkillLevel::factory()->create();
    $this->actingAs($account)->withSession(['account_session_version' => $account->session_version]);
    $this->get('/employees/'.$account->employee_id.'/skills/'.$level->skill_id.'/assessments/self/edit')->assertOk()->assertSee('Self assessment')->assertSee($level->label);
});

it('keeps self and manager ratings independent with immutable historical meaning', function (): void {
    $manager = User::factory()->withEmployee()->create();
    $account = User::factory()->withEmployee()->create();
    $account->employee->forceFill(['manager_id' => $manager->employee_id])->save();
    $skill = Skill::factory()->create(['name' => 'Original skill label']);
    $zero = SkillLevel::factory()->create(['skill_id' => $skill->id, 'ordinal' => 0]);
    $high = SkillLevel::factory()->create(['skill_id' => $skill->id, 'ordinal' => 2]);
    $url = '/employees/'.$account->employee_id.'/skills/'.$skill->id.'/assessments';
    $this->actingAs($account)->withSession(['account_session_version' => $account->session_version]);
    $this->post($url, ['kind' => 'self', 'state' => 'rated', 'skill_level_id' => $zero->id, 'assessed_on' => now()->toDateString(), 'comment' => 'Self explanation', 'expected_revision' => 0])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($manager)->withSession(['account_session_version' => $manager->session_version]);
    $this->post($url, ['kind' => 'manager', 'state' => 'rated', 'skill_level_id' => $high->id, 'assessed_on' => now()->toDateString(), 'comment' => 'Confidential manager notes', 'expected_revision' => 0, 'actor_id' => $account->id])->assertRedirect()->assertSessionHasNoErrors();
    $this->post($url, ['kind' => 'manager', 'state' => 'rated', 'skill_level_id' => $zero->id, 'assessed_on' => now()->toDateString(), 'comment' => 'Later manager assessment', 'expected_revision' => 1])->assertRedirect()->assertSessionHasNoErrors();
    expect(Assessment::query()->count())->toBe(2);
    expect(AssessmentRevision::query()->count())->toBe(3);
    $self = Assessment::query()->where('kind', 'self')->firstOrFail();
    expect($self->currentRevision->skill_level_id)->toBe($zero->id);
    expect(AssessmentRevision::query()->where('comment', 'Confidential manager notes')->firstOrFail()->actor_id)->toBe($manager->id);
    $skill->update(['name' => 'Renamed current skill']);
    expect($self->currentRevision->snapshot['skill_name'])->toBe('Original skill label');
    $this->get('/assessments/'.$self->id)->assertOk()->assertSee('Self explanation');
});

it('retains private evidence on old revisions and checks current download scope', function (): void {
    $account = User::factory()->withEmployee()->create();
    $manager = User::factory()->withEmployee()->create();
    $account->employee->forceFill(['manager_id' => $manager->employee_id])->save();
    $level = SkillLevel::factory()->create();
    $url = '/employees/'.$account->employee_id.'/skills/'.$level->skill_id.'/assessments';
    $this->actingAs($account)->withSession(['account_session_version' => $account->session_version]);
    $data = ['kind' => 'self', 'state' => 'rated', 'skill_level_id' => $level->id, 'assessed_on' => now()->toDateString(), 'expected_revision' => 0];
    $this->post($url, $data + ['attachment' => UploadedFile::fake()->createWithContent('evidence.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF")])->assertRedirect()->assertSessionHasNoErrors();
    $attachment = EvidenceAttachment::query()->firstOrFail();
    Storage::disk('evidence')->assertExists($attachment->storage_path);
    $this->post($url, array_replace($data, ['expected_revision' => 1, 'comment' => 'Newer revision without replacement']))->assertRedirect()->assertSessionHasNoErrors();
    expect(EvidenceAttachment::query()->count())->toBe(1);
    $this->get('/evidence/'.$attachment->id)->assertOk()->assertDownload('evidence.pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->assertDatabaseHas('audit_events', ['event' => 'evidence.downloaded', 'subject_id' => $attachment->id]);
    $this->actingAs($manager)->withSession(['account_session_version' => $manager->session_version]);
    $this->get('/evidence/'.$attachment->id)->assertOk();
    $account->employee->forceFill(['manager_id' => null])->save();
    $this->get('/evidence/'.$attachment->id)->assertForbidden();
});

it('does not include confidential manager drafts or attachment metadata in employee responses', function (): void {
    $manager = User::factory()->withEmployee()->create();
    $account = User::factory()->withEmployee()->create();
    $account->employee->forceFill(['manager_id' => $manager->employee_id])->save();
    $level = SkillLevel::factory()->create();
    $this->actingAs($manager)->withSession(['account_session_version' => $manager->session_version]);
    $this->post('/employees/'.$account->employee_id.'/skills/'.$level->skill_id.'/assessments', ['kind' => 'manager', 'state' => 'rated', 'skill_level_id' => $level->id, 'assessed_on' => now()->toDateString(), 'comment' => 'PrivateManagerMarker', 'evidence_notes' => 'PrivateEvidenceMarker', 'expected_revision' => 0, 'attachment' => UploadedFile::fake()->createWithContent('private-manager.pdf', "%PDF-1.4\n%%EOF")])->assertRedirect()->assertSessionHasNoErrors();
    $assessment = Assessment::query()->firstOrFail();
    $attachment = EvidenceAttachment::query()->firstOrFail();
    $this->actingAs($account)->withSession(['account_session_version' => $account->session_version]);
    $this->get('/employees/'.$account->employee_id.'/assessments')->assertOk()->assertDontSee('PrivateManagerMarker')->assertDontSee('PrivateEvidenceMarker')->assertDontSee('private-manager.pdf');
    $this->get('/assessments/'.$assessment->id)->assertForbidden();
    $this->get('/evidence/'.$attachment->id)->assertForbidden();
});

it('rejects stale writes and removes staged files without losing earlier evidence', function (): void {
    $account = User::factory()->withEmployee()->create();
    $level = SkillLevel::factory()->create();
    $url = '/employees/'.$account->employee_id.'/skills/'.$level->skill_id.'/assessments';
    $data = ['kind' => 'self', 'state' => 'rated', 'skill_level_id' => $level->id, 'assessed_on' => now()->toDateString(), 'expected_revision' => 0, 'comment' => 'First revision'];
    $this->actingAs($account)->withSession(['account_session_version' => $account->session_version]);
    $this->post($url, $data)->assertRedirect()->assertSessionHasNoErrors();
    $this->from('/dashboard')->post($url, array_replace($data, ['comment' => 'Unsaved stale input', 'attachment' => UploadedFile::fake()->createWithContent('stale.pdf', "%PDF-1.4\n%%EOF")]))->assertSessionHasErrors('expected_revision')->assertSessionHasInput('comment', 'Unsaved stale input');
    expect(AssessmentRevision::query()->count())->toBe(1);
    expect(Storage::disk('evidence')->allFiles())->toBe([]);
});

it('refuses forged manager ratings and other peoples self ratings', function (): void {
    $account = User::factory()->withEmployee()->create();
    $outside = Employee::factory()->create();
    $level = SkillLevel::factory()->create();
    $this->actingAs($account)->withSession(['account_session_version' => $account->session_version]);
    $data = ['state' => 'rated', 'skill_level_id' => $level->id, 'assessed_on' => now()->toDateString(), 'expected_revision' => 0];
    foreach ([['employee' => $account->employee_id, 'kind' => 'manager'], ['employee' => $outside->id, 'kind' => 'self']] as $forged) {
        $this->post('/employees/'.$forged['employee'].'/skills/'.$level->skill_id.'/assessments', $data + ['kind' => $forged['kind']])->assertForbidden();
    }
    expect(AssessmentRevision::query()->count())->toBe(0);
});

it('requires evidence for rated skills and validates date and rating membership', function (): void {
    $account = User::factory()->withEmployee()->create();
    $skill = Skill::factory()->create(['requires_evidence' => true]);
    $level = SkillLevel::factory()->create(['skill_id' => $skill->id]);
    $other = SkillLevel::factory()->create();
    $this->actingAs($account)->withSession(['account_session_version' => $account->session_version]);
    $url = '/employees/'.$account->employee_id.'/skills/'.$skill->id.'/assessments';
    $data = ['kind' => 'self', 'state' => 'rated', 'skill_level_id' => $level->id, 'assessed_on' => now()->toDateString(), 'expected_revision' => 0];
    $this->post($url, $data)->assertSessionHasErrors('evidence_notes');
    $this->post($url, array_replace($data, ['skill_level_id' => $other->id, 'evidence_notes' => 'Evidence']))->assertSessionHasErrors('skill_level_id');
    $this->post($url, array_replace($data, ['assessed_on' => now()->addDay()->toDateString(), 'evidence_notes' => 'Evidence']))->assertSessionHasErrors('assessed_on');
    $this->post($url, $data + ['evidence_notes' => 'Dated observed demonstration'])->assertRedirect()->assertSessionHasNoErrors();
});

it('keeps permitted N/A and unassessed separate from a rated zero', function (): void {
    $account = User::factory()->withEmployee()->create();
    $skill = Skill::factory()->create(['allows_na' => true]);
    $zero = SkillLevel::factory()->create(['skill_id' => $skill->id, 'ordinal' => 0]);
    $this->actingAs($account)->withSession(['account_session_version' => $account->session_version]);
    $url = '/employees/'.$account->employee_id.'/skills/'.$skill->id.'/assessments';
    $data = ['kind' => 'self', 'assessed_on' => now()->toDateString(), 'expected_revision' => 0];
    $this->post($url, $data + ['state' => 'not_applicable'])->assertSessionHasErrors('comment');
    $this->post($url, $data + ['state' => 'not_applicable', 'comment' => 'Not part of current duties'])->assertRedirect()->assertSessionHasNoErrors();
    $this->post($url, array_replace($data, ['state' => 'unassessed', 'expected_revision' => 1]))->assertRedirect()->assertSessionHasNoErrors();
    $this->post($url, array_replace($data, ['state' => 'rated', 'skill_level_id' => $zero->id, 'expected_revision' => 2]))->assertRedirect()->assertSessionHasNoErrors();
    expect(AssessmentRevision::query()->orderBy('revision')->pluck('state')->map(fn ($state) => $state->value)->all())->toBe(['not_applicable', 'unassessed', 'rated']);
    expect(Assessment::query()->firstOrFail()->currentRevision->snapshot['ordinal'])->toBe(0);
});

it('rejects invalid and oversized uploads before storing an assessment', function (): void {
    $account = User::factory()->withEmployee()->create();
    $level = SkillLevel::factory()->create();
    $this->actingAs($account)->withSession(['account_session_version' => $account->session_version]);
    $url = '/employees/'.$account->employee_id.'/skills/'.$level->skill_id.'/assessments';
    $data = ['kind' => 'self', 'state' => 'rated', 'skill_level_id' => $level->id, 'assessed_on' => now()->toDateString(), 'expected_revision' => 0];
    foreach ([UploadedFile::fake()->createWithContent('bad.php', '<?php echo 1;'), UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf')] as $file) {
        $this->post($url, $data + ['attachment' => $file])->assertSessionHasErrors('attachment');
    }
    expect(AssessmentRevision::query()->count())->toBe(0);
    expect(Storage::disk('evidence')->allFiles())->toBe([]);
});

it('refuses a changed evidence file instead of returning untrusted replacement content', function (): void {
    $account = User::factory()->withEmployee()->create();
    $level = SkillLevel::factory()->create();
    $this->actingAs($account)->withSession(['account_session_version' => $account->session_version]);
    $this->post('/employees/'.$account->employee_id.'/skills/'.$level->skill_id.'/assessments', ['kind' => 'self', 'state' => 'rated', 'skill_level_id' => $level->id, 'assessed_on' => now()->toDateString(), 'expected_revision' => 0, 'attachment' => UploadedFile::fake()->createWithContent('example.pdf', "%PDF-1.4\n%%EOF")])->assertRedirect()->assertSessionHasNoErrors();
    $attachment = EvidenceAttachment::query()->firstOrFail();
    Storage::disk('evidence')->put($attachment->storage_path, 'Replacement private content');
    $this->get('/evidence/'.$attachment->id)->assertStatus(409)->assertDontSee('Replacement private content');
});

it('protects immutable assessment revisions from model updates and deletes', function (): void {
    $revision = AssessmentRevision::factory()->create();
    expect(fn () => $revision->update(['comment' => 'Overwrite']))->toThrow(LogicException::class);
    expect(fn () => $revision->delete())->toThrow(LogicException::class);
});

it('leaves no assessment write when private storage is unavailable', function (): void {
    $account = User::factory()->withEmployee()->create();
    $level = SkillLevel::factory()->create();
    $file = UploadedFile::fake()->createWithContent('example.pdf', "%PDF-1.4\n%%EOF");
    Storage::shouldReceive('disk')->with('evidence')->andThrow(new RuntimeException('Storage unavailable'));
    expect(fn () => app(SaveAssessment::class)->handle($account, $account->employee, $level->skill, ['kind' => 'self', 'state' => 'rated', 'skill_level_id' => $level->id, 'assessed_on' => now()->toDateString(), 'expected_revision' => 0], $file))->toThrow(RuntimeException::class, 'Storage unavailable');
    expect(Assessment::query()->count())->toBe(0);
    expect(EvidenceAttachment::query()->count())->toBe(0);
});

it('keeps authorised historical assessments discoverable after retirement and changed duties', function (): void {
    $account = User::factory()->withEmployee()->create();
    $manager = User::factory()->withEmployee()->create();
    $account->employee->forceFill(['manager_id' => $manager->employee_id])->save();
    $retired = Skill::factory()->create(['name' => 'RetiredSkillHistoryMarker']);
    $changed = Skill::factory()->create(['name' => 'ChangedDutiesHistoryMarker']);
    $this->actingAs($account)->withSession(['account_session_version' => $account->session_version]);
    foreach ([$retired, $changed] as $skill) {
        $level = SkillLevel::factory()->create(['skill_id' => $skill->id]);
        $this->post('/employees/'.$account->employee_id.'/skills/'.$skill->id.'/assessments', ['kind' => 'self', 'state' => 'rated', 'skill_level_id' => $level->id, 'assessed_on' => now()->toDateString(), 'expected_revision' => 0])->assertRedirect()->assertSessionHasNoErrors();
    }
    $this->actingAs($manager)->withSession(['account_session_version' => $manager->session_version]);
    $level = $retired->levels()->firstOrFail();
    $this->post('/employees/'.$account->employee_id.'/skills/'.$retired->id.'/assessments', ['kind' => 'manager', 'state' => 'rated', 'skill_level_id' => $level->id, 'assessed_on' => now()->toDateString(), 'expected_revision' => 0])->assertRedirect()->assertSessionHasNoErrors();
    $retired->update(['is_active' => false]);
    $changed->departments()->attach(Department::factory()->create());
    $this->actingAs($account)->withSession(['account_session_version' => $account->session_version]);
    $response = $this->get('/employees/'.$account->employee_id.'/assessments')->assertOk()->assertSee('RetiredSkillHistoryMarker')->assertSee('ChangedDutiesHistoryMarker');
    $archive = $response->viewData('archivedAssessments');
    expect(count($archive))->toBe(2);
    foreach ($archive as $entry) {
        expect($entry['kind'])->toBe('self');
        $this->get('/assessments/'.$entry['id'])->assertOk();
    }
    $this->get('/employees/'.$account->employee_id.'/skills/'.$retired->id.'/assessments/self/edit')->assertNotFound();
});
