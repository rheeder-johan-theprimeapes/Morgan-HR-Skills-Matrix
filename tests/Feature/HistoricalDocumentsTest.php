<?php

declare(strict_types=1);

use App\Actions\SaveHistoricalDocument;
use App\Capability;
use App\DocumentVisibility;
use App\Models\Assessment;
use App\Models\AuditEvent;
use App\Models\DocumentVisibilityRevision;
use App\Models\HistoricalDocument;
use App\Models\PayRecord;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToWriteFile;

uses(LazilyRefreshDatabase::class);

function historicalFixture(): array
{
    Storage::fake('evidence');
    $hr = User::factory()->withEmployee()->create(['capabilities' => [Capability::AdministerReviews->value, Capability::ActualPay->value, Capability::SensitiveContext->value, Capability::ViewOrganisation->value]]);
    $manager = User::factory()->withEmployee()->create();
    $employee = User::factory()->withEmployee()->create();
    $employee->employee->forceFill(['manager_id' => $manager->employee_id])->save();

    return [$hr, $manager, $employee];
}
function historicalInput(string $visibility = 'hr_only'): array
{
    return ['expected_revision' => 0, 'title' => 'Fictional historical PDR', 'document_date' => '2025-04-01', 'source' => 'Fictional HR archive reference 2025-04', 'visibility' => $visibility, 'reason' => 'Initial historical archive', 'content_review_confirmed' => $visibility !== 'hr_only'];
}
function historicalFile(string $name = 'fictional-history.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, '%PDF-1.4
Fictional historical document only');
}
function historicalLogin(User $user): void
{
    test()->actingAs($user)->withSession(['account_session_version' => $user->session_version]);
}
function historicalUpload(User $hr, User $employee, string $visibility = 'hr_only'): HistoricalDocument
{
    return app(SaveHistoricalDocument::class)->handle($hr, $employee->employee, null, historicalInput($visibility), historicalFile());
}

it('uploads immutable private historical provenance without creating structured records', function (): void {
    [$hr, $manager, $employee] = historicalFixture();
    historicalLogin($hr);
    $this->post('/employees/'.$employee->employee_id.'/historical-documents', historicalInput() + ['attachment' => historicalFile()])->assertSessionHasNoErrors()->assertRedirect();
    $document = HistoricalDocument::query()->sole();
    expect($document->revision)->toBe(1)->and($document->visibility)->toBe(DocumentVisibility::HrOnly)->and(Storage::disk('evidence')->exists($document->storage_path))->toBeTrue();
    $this->get('/historical-documents/'.$document->id)->assertOk()->assertSee('Unstructured historical document')->assertSee('Fictional HR archive reference 2025-04')->assertDontSee($document->storage_path);
    $this->get('/historical-documents/'.$document->id.'/download')->assertOk()->assertDownload('fictional-history.pdf')->assertHeader('x-content-type-options', 'nosniff');
    expect(AuditEvent::query()->where('event', 'historical_document.downloaded')->count())->toBe(1);
    expect(Assessment::query()->count())->toBe(0)->and(PayRecord::query()->count())->toBe(0);
    expect(fn () => $document->update(['title' => 'Replaced']))->toThrow(LogicException::class);
    expect(fn () => $document->delete())->toThrow(LogicException::class);
});

it('filters hidden rows and denies guessed identifiers without revealing private administrative reasons', function (): void {
    [$hr,$manager,$employee] = historicalFixture();
    $hidden = historicalUpload($hr, $employee);
    $shared = historicalUpload($hr, $employee, 'employee_shared');
    historicalLogin($employee);
    $response = $this->get('/employees/'.$employee->employee_id.'/historical-documents')->assertOk();
    expect($response->viewData('documents')->total())->toBe(1);
    $this->get('/historical-documents/'.$hidden->id)->assertForbidden();
    $this->get('/historical-documents/'.$hidden->id.'/download')->assertForbidden();
    $response = $this->get('/historical-documents/'.$shared->id)->assertOk()->assertDontSee('Initial historical archive');
    expect(json_encode($response->viewData('document')))->not->toContain('storage_path', 'sha256', 'uploaded_by', 'Initial historical archive');
    historicalLogin($manager);
    $this->get('/historical-documents/'.$hidden->id.'/download')->assertForbidden();
    $this->get('/historical-documents/'.$shared->id.'/download')->assertOk();
});

it('requires explicit reviewed visibility and records immutable revocation history', function (): void {
    [$hr,$manager,$employee] = historicalFixture();
    $document = historicalUpload($hr, $employee);
    historicalLogin($hr);
    $url = '/historical-documents/'.$document->id.'/visibility';
    $input = ['expected_revision' => 1, 'visibility' => 'supervisors', 'reason' => 'Reviewed file for current supervisors'];
    $this->postJson($url, $input)->assertUnprocessable()->assertJsonValidationErrors('content_review_confirmed');
    $this->post($url, $input + ['content_review_confirmed' => true])->assertSessionHasNoErrors();
    historicalLogin($manager);
    $this->get('/historical-documents/'.$document->id.'/download')->assertOk();
    historicalLogin($employee);
    $this->get('/historical-documents/'.$document->id.'/download')->assertForbidden();
    historicalLogin($hr);
    $this->post($url, ['expected_revision' => 2, 'visibility' => 'hr_only', 'reason' => 'Withdraw broad sharing'])->assertSessionHasNoErrors();
    expect($document->refresh()->revision)->toBe(3)->and(DocumentVisibilityRevision::query()->count())->toBe(3);
    $history = DocumentVisibilityRevision::query()->first();
    expect(fn () => $history->update(['reason' => 'Rewrite']))->toThrow(LogicException::class);
    historicalLogin($manager);
    $this->get('/historical-documents/'.$document->id.'/download')->assertForbidden();
});

it('denies uploads and sharing to employees supervisors organisation readers and incomplete HR grants', function (string $role): void {
    [$hr,$manager,$employee] = historicalFixture();
    $document = historicalUpload($hr, $employee);
    $actor = match ($role) {
        'employee' => $employee,'manager' => $manager,'organisation' => User::factory()->withEmployee()->create(['capabilities' => [Capability::ViewOrganisation->value]]),default => User::factory()->withEmployee()->create(['capabilities' => [Capability::AdministerReviews->value, Capability::ViewOrganisation->value]])
    };
    historicalLogin($actor);
    $this->post('/employees/'.$employee->employee_id.'/historical-documents', historicalInput() + ['attachment' => historicalFile()])->assertForbidden();
    $this->post('/historical-documents/'.$document->id.'/visibility', ['expected_revision' => 1, 'visibility' => 'employee_shared', 'reason' => 'Forged', 'content_review_confirmed' => true])->assertForbidden();
    expect(HistoricalDocument::query()->count())->toBe(1)->and(DocumentVisibilityRevision::query()->count())->toBe(1);
})->with(['employee', 'manager', 'organisation', 'incomplete-hr']);

it('rejects unsafe types names sizes and forged identity metadata atomically', function (string $invalid): void {
    [$hr,$manager,$employee] = historicalFixture();
    $input = historicalInput();
    $file = historicalFile();
    if ($invalid === 'type') {
        $fake = UploadedFile::fake()->createWithContent('archive.pdf', '<script>unsafe</script>');
        $file = new UploadedFile($fake->getPathname(), 'archive.pdf', null, null, true);
    }
    if ($invalid === 'extension') {
        $file = historicalFile('archive.html');
    }
    if ($invalid === 'name') {
        $file = historicalFile('unsafename.pdf');
    }
    if ($invalid === 'size') {
        $file = UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf');
    }
    if ($invalid === 'identity') {
        $input['uploaded_by'] = $employee->id;
    }
    if ($invalid === 'future') {
        $input['document_date'] = today()->addDay()->toDateString();
    }
    historicalLogin($hr);
    $this->postJson('/employees/'.$employee->employee_id.'/historical-documents', $input + ['attachment' => $file])->assertUnprocessable();
    expect(HistoricalDocument::query()->count())->toBe(0)->and(Storage::disk('evidence')->allFiles())->toBe([]);
})->with(['type', 'extension', 'name', 'size', 'identity', 'future']);

it('retains stale visibility input and prevents stale expansion after a competing change', function (): void {
    [$hr,$manager,$employee] = historicalFixture();
    $document = historicalUpload($hr, $employee);
    historicalLogin($hr);
    $url = '/historical-documents/'.$document->id;
    $this->withCookie((string) config('session.cookie'), session()->getId());
    $this->get($url)->assertOk();
    app(SaveHistoricalDocument::class)->handle($hr, $employee->employee, $document, ['expected_revision' => 1, 'visibility' => 'supervisors', 'reason' => 'Competing visibility', 'content_review_confirmed' => true]);
    $this->from($url)->post($url.'/visibility', ['expected_revision' => 1, 'visibility' => 'employee_shared', 'reason' => 'Retained rejected explanation', 'content_review_confirmed' => true])->assertSessionHasErrors('expected_revision');
    $this->get($url)->assertOk()->assertSee('Retained rejected explanation')->assertSee('value="1"', false);
    expect($document->refresh()->visibility)->toBe(DocumentVisibility::Supervisors)->and($document->revision)->toBe(2);
});

it('rechecks revoked grants changed reporting and disabled accounts on every retrieval', function (): void {
    [$hr,$manager,$employee] = historicalFixture();
    $document = historicalUpload($hr, $employee, 'employee_shared');
    historicalLogin($manager);
    $this->get('/historical-documents/'.$document->id.'/download')->assertOk();
    $employee->employee->forceFill(['manager_id' => null])->save();
    $this->get('/historical-documents/'.$document->id.'/download')->assertForbidden();
    historicalLogin($hr);
    $hr->forceFill(['capabilities' => [Capability::AdministerReviews->value, Capability::ViewOrganisation->value]])->save();
    $this->get('/historical-documents/'.$document->id.'/download')->assertForbidden();
    historicalLogin($employee);
    $employee->forceFill(['is_active' => false])->save();
    $this->get('/historical-documents/'.$document->id.'/download')->assertRedirect('/login');
});

it('fails closed for missing or tampered private bytes and offers no public attachment URL', function (): void {
    [$hr,$manager,$employee] = historicalFixture();
    $document = historicalUpload($hr, $employee, 'employee_shared');
    historicalLogin($employee);
    Storage::disk('evidence')->put($document->storage_path, 'changed');
    $this->get('/historical-documents/'.$document->id.'/download')->assertStatus(409);
    Storage::disk('evidence')->delete($document->storage_path);
    $this->get('/historical-documents/'.$document->id.'/download')->assertNotFound();
    $this->get('/storage/'.$document->storage_path)->assertForbidden();
    expect(AuditEvent::query()->where('event', 'historical_document.downloaded')->count())->toBe(0);
});

it('cleans staged bytes when transactional audit failure rolls back metadata', function (): void {
    [$hr,$manager,$employee] = historicalFixture();
    AuditEvent::creating(function (AuditEvent $event): void {
        if ($event->event === 'historical_document.uploaded') {
            throw new RuntimeException('Deliberate audit failure');
        }
    });
    try {
        expect(fn () => historicalUpload($hr, $employee))->toThrow(RuntimeException::class);
    } finally {
        AuditEvent::flushEventListeners();
    }
    expect(HistoricalDocument::query()->count())->toBe(0)->and(DocumentVisibilityRevision::query()->count())->toBe(0)->and(Storage::disk('evidence')->allFiles())->toBe([]);
});

it('reports failed uploads without orphaned metadata or partial bytes', function (): void {
    [$hr,$manager,$employee] = historicalFixture();
    $disk = Storage::disk('evidence');
    $adapter = Mockery::mock(FilesystemAdapter::class)->makePartial();
    $adapter->shouldReceive('putFileAs')->once()->andReturnUsing(function (string $folder, UploadedFile $file, string $name) use ($disk): never {
        $disk->put($folder.'/'.$name, 'partial');
        throw new UnableToWriteFile('Deliberate write failure');
    });
    $adapter->shouldReceive('delete')->once()->andReturnUsing(fn (string $path): bool => $disk->delete($path));
    Storage::set('evidence', $adapter);
    historicalLogin($hr);
    $this->post('/employees/'.$employee->employee_id.'/historical-documents', historicalInput() + ['attachment' => historicalFile()])->assertSessionHasErrors('attachment');
    expect(HistoricalDocument::query()->count())->toBe(0)->and($disk->allFiles())->toBe([]);
});
