<?php

declare(strict_types=1);

use App\Capability;
use App\Models\ScoringFramework;
use App\Models\Skill;
use App\Models\SkillLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function frameworkInput(): array
{
    $skill = Skill::factory()->create(['allows_na' => true]);
    $zero = SkillLevel::factory()->for($skill)->create(['ordinal' => 0, 'label' => 'Beginning']);
    $top = SkillLevel::factory()->for($skill)->create(['ordinal' => 4, 'label' => 'Independent']);

    return ['form_complete' => 1, 'name' => 'Illustrative framework', 'illustrative' => true, 'approval_reference' => null, 'expected_revision' => 0,
        'components' => [['group_id' => $skill->skill_group_id, 'weight' => 2, 'skills' => [['skill_id' => $skill->id, 'included' => true, 'required' => true, 'requires_evidence' => true, 'levels' => [['level_id' => $zero->id, 'points' => '0.00'], ['level_id' => $top->id, 'points' => '100.00']]]]]],
        'bands' => [['label' => 'Developing', 'from' => '0.00', 'to' => '50.00'], ['label' => 'Established', 'from' => '50.00', 'to' => '100.00']]];
}

function frameworkHr(): User
{
    return User::factory()->withEmployee()->create(['capabilities' => [Capability::ManageFrameworks->value]]);
}

it('drafts publishes and creates an independent successor with frozen meanings', function (): void {
    $hr = frameworkHr();
    $input = frameworkInput();
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->get('/frameworks/create')->assertOk()->assertSee('Illustrative');
    $this->post('/frameworks', $input)->assertRedirect()->assertSessionHasNoErrors();
    $framework = ScoringFramework::query()->sole();
    expect($framework->published_at)->toBeNull();
    $this->get('/frameworks/'.$framework->id.'/edit')->assertOk()->assertSee('Beginning');
    $this->post('/frameworks/'.$framework->id.'/publish', ['expected_revision' => 1])->assertRedirect()->assertSessionHasNoErrors();
    $framework->refresh();
    $snapshot = $framework->definition;
    expect($framework->published_at)->not->toBeNull();
    expect($snapshot['components'][0]['skills'][0]['levels'][1]['points'])->toBe(10000);
    $this->get('/frameworks/'.$framework->id)->assertOk()->assertSee('Illustrative')->assertSee('Established');
    $this->post('/frameworks/'.$framework->id.'/successor')->assertRedirect()->assertSessionHasNoErrors();
    $successor = ScoringFramework::query()->where('predecessor_id', $framework->id)->sole();
    expect($successor->published_at)->toBeNull()->and($successor->definition)->toBe($snapshot);
    Skill::query()->whereKey($input['components'][0]['skills'][0]['skill_id'])->update(['name' => 'Later catalogue name']);
    expect($framework->fresh()->definition)->toBe($snapshot);
    $this->assertDatabaseHas('audit_events', ['event' => 'framework.published', 'subject_id' => $framework->id]);
});

it('refuses published edits deletion and direct model mutation', function (): void {
    $hr = frameworkHr();
    $input = frameworkInput();
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->post('/frameworks', $input);
    $f = ScoringFramework::query()->sole();
    $this->post('/frameworks/'.$f->id.'/publish', ['expected_revision' => 1])->assertSessionHasNoErrors();
    $input['expected_revision'] = 2;
    $this->put('/frameworks/'.$f->id, $input)->assertStatus(409);
    $this->get('/frameworks/'.$f->id.'/edit')->assertStatus(409);
    $this->delete('/frameworks/'.$f->id)->assertStatus(405);
    expect(fn () => $f->refresh()->update(['name' => 'Tampered']))->toThrow(LogicException::class);
    expect(fn () => $f->refresh()->delete())->toThrow(LogicException::class);
});

it('rejects stale draft saves and publication without losing the current draft', function (): void {
    $hr = frameworkHr();
    $input = frameworkInput();
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->post('/frameworks', $input);
    $f = ScoringFramework::query()->sole();
    $this->put('/frameworks/'.$f->id, $input)->assertSessionHasErrors('expected_revision');
    $this->post('/frameworks/'.$f->id.'/publish', ['expected_revision' => 0])->assertSessionHasErrors('expected_revision');
    expect($f->refresh()->revision)->toBe(1)->and($f->published_at)->toBeNull();
});

it('requires explicit current authority for every framework entry point', function (): void {
    $user = User::factory()->directoryAdministrator()->accountAdministrator()->create();
    $input = frameworkInput();
    $this->actingAs($user)->withSession(['account_session_version' => $user->session_version]);
    $this->get('/frameworks')->assertForbidden();
    $this->get('/frameworks/create')->assertForbidden();
    $this->post('/frameworks', $input)->assertForbidden();
    expect(ScoringFramework::query()->count())->toBe(0);
});

it('rejects incomplete nonmonotonic and ambiguous definitions at publication', function (string $fault): void {
    $hr = frameworkHr();
    $input = frameworkInput();
    if ($fault === 'points') {
        $input['components'][0]['skills'][0]['levels'][1]['points'] = '0.00';
    }
    if ($fault === 'gap') {
        $input['bands'][1]['from'] = '50.01';
    }
    if ($fault === 'overlap') {
        $input['bands'][1]['from'] = '49.99';
    }
    if ($fault === 'end') {
        $input['bands'][1]['to'] = '99.99';
    }
    if ($fault === 'empty') {
        $input['components'][0]['skills'][0]['included'] = false;
    }
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->post('/frameworks', $input)->assertSessionHasNoErrors();
    $f = ScoringFramework::query()->sole();
    $this->post('/frameworks/'.$f->id.'/publish', ['expected_revision' => 1])->assertSessionHasErrors('definition');
    expect($f->refresh()->published_at)->toBeNull();
    $this->assertDatabaseMissing('audit_events', ['event' => 'framework.published']);
})->with(['points', 'gap', 'overlap', 'end', 'empty']);

it('refuses invalid precision weights duplicate skills and forged rating references', function (string $fault): void {
    $hr = frameworkHr();
    $input = frameworkInput();
    if ($fault === 'precision') {
        $input['components'][0]['skills'][0]['levels'][1]['points'] = '99.999';
    }
    if ($fault === 'weight') {
        $input['components'][0]['weight'] = 0;
    }
    if ($fault === 'duplicate') {
        $input['components'][0]['skills'][] = $input['components'][0]['skills'][0];
    }
    if ($fault === 'reference') {
        $input['components'][0]['skills'][0]['levels'][0]['level_id'] = SkillLevel::factory()->create()->id;
    }
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->post('/frameworks', $input)->assertSessionHasErrors();
    expect(ScoringFramework::query()->count())->toBe(0);
})->with(['precision', 'weight', 'duplicate', 'reference']);

it('revalidates retired references before publish and requires live HR approval', function (): void {
    $hr = frameworkHr();
    $input = frameworkInput();
    $input['illustrative'] = false;
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->post('/frameworks', $input)->assertSessionHasNoErrors();
    $f = ScoringFramework::query()->sole();
    $this->post('/frameworks/'.$f->id.'/publish', ['expected_revision' => 1])->assertSessionHasErrors('approval_reference');
    $input['approval_reference'] = 'Synthetic test approval reference';
    $input['expected_revision'] = 1;
    $this->put('/frameworks/'.$f->id, $input)->assertSessionHasNoErrors();
    SkillLevel::query()->whereKey($input['components'][0]['skills'][0]['levels'][0]['level_id'])->update(['is_active' => false]);
    $this->post('/frameworks/'.$f->id.'/publish', ['expected_revision' => 2])->assertSessionHasErrors('definition');
    expect($f->refresh()->published_at)->toBeNull();
});

it('keeps stale form values paired with their original revision through repeat submissions', function (): void {
    $hr = frameworkHr();
    $input = frameworkInput();
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->post('/frameworks', $input);
    $framework = ScoringFramework::query()->sole();
    $input['expected_revision'] = 1;
    $input['name'] = 'Concurrent editor saved this';
    $this->put('/frameworks/'.$framework->id, $input)->assertSessionHasNoErrors();
    $input['name'] = 'Stale editor unsaved values';
    $this->from('/frameworks/'.$framework->id.'/edit')->put('/frameworks/'.$framework->id, $input)->assertSessionHasErrors('expected_revision');
    $response = $this->get('/frameworks/'.$framework->id.'/edit')->assertOk();
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $token = (new DOMXPath($document))->query('//input[@name="expected_revision"]')->item(0)->getAttribute('value');
    expect($token)->toBe('1');
    $response->assertSee('Discard unsaved changes');
    $input['expected_revision'] = (int) $token;
    $this->put('/frameworks/'.$framework->id, $input)->assertSessionHasErrors('expected_revision');
    expect($framework->refresh()->name)->toBe('Concurrent editor saved this');
});

it('rejects a truncated framework editor submission without saving a partial definition', function (): void {
    $hr = frameworkHr();
    $input = frameworkInput();
    unset($input['form_complete']);
    $this->actingAs($hr)->withSession(['account_session_version' => $hr->session_version]);
    $this->post('/frameworks', $input)->assertSessionHasErrors('form_complete');
    expect(ScoringFramework::query()->count())->toBe(0);
});
