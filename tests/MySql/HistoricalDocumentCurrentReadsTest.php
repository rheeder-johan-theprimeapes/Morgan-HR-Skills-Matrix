<?php

declare(strict_types=1);
use App\Actions\SaveHistoricalDocument;
use App\Capability;
use App\Models\DocumentVisibilityRevision;
use App\Models\Employee;
use App\Models\HistoricalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(DatabaseTruncation::class);
function committedHistoricalFixture(): array
{
    Storage::fake('evidence');
    $hr = User::factory()->withEmployee()->create(['capabilities' => [Capability::AdministerReviews->value, Capability::ActualPay->value, Capability::SensitiveContext->value, Capability::ViewOrganisation->value]]);
    $person = Employee::factory()->create();
    $document = app(SaveHistoricalDocument::class)->handle($hr, $person, null, ['expected_revision' => 0, 'visibility' => 'hr_only', 'title' => 'Fictional archive', 'document_date' => '2025-01-01', 'source' => 'Fictional history reference', 'reason' => 'Initial archive'], UploadedFile::fake()->createWithContent('archive.pdf', '%PDF-1.4
Fictional committed archive'));

    return [$hr, $person, $document];
}
it('uses current locked visibility after a competing committed revision', function (): void {
    [$hr,$person,$document] = committedHistoricalFixture();
    $settings = config('database.connections.mysql');
    expect($settings['database'])->toBe('morgan_hr_skills_test');
    config(['database.connections.historical_competitor' => $settings]);
    expect(DB::connection('historical_competitor')->selectOne('SELECT DATABASE() AS name')->name)->toBe('morgan_hr_skills_test');
    DB::transaction(function () use ($hr, $person, $document): void {
        expect(HistoricalDocument::query()->findOrFail($document->id)->revision)->toBe(1);
        DB::setDefaultConnection('historical_competitor');
        try {
            app(SaveHistoricalDocument::class)->handle($hr, $person, $document, ['expected_revision' => 1, 'visibility' => 'supervisors', 'reason' => 'Committed competing choice', 'content_review_confirmed' => true]);
        } finally {
            DB::setDefaultConnection('mysql');
        }
        expect(fn () => app(SaveHistoricalDocument::class)->handle($hr, $person, $document, ['expected_revision' => 1, 'visibility' => 'employee_shared', 'reason' => 'Losing expansion', 'content_review_confirmed' => true]))->toThrow(ValidationException::class);
    });
    expect($document->fresh()->revision)->toBe(2)->and(DocumentVisibilityRevision::query()->count())->toBe(2);
    DB::purge('historical_competitor');
});
it('refuses visibility administration after capability revocation beyond an older snapshot', function (): void {
    [$hr,$person,$document] = committedHistoricalFixture();
    $settings = config('database.connections.mysql');
    expect($settings['database'])->toBe('morgan_hr_skills_test');
    $other = DB::build($settings);
    expect($other->selectOne('SELECT DATABASE() AS name')->name)->toBe('morgan_hr_skills_test');
    DB::transaction(function () use ($hr, $person, $document, $other): void {
        expect(User::query()->findOrFail($hr->id)->capabilities)->toContain(Capability::SensitiveContext->value);
        $other->table('users')->where('id', $hr->id)->update(['capabilities' => json_encode([Capability::AdministerReviews->value, Capability::ViewOrganisation->value])]);
        expect(fn () => app(SaveHistoricalDocument::class)->handle($hr, $person, $document, ['expected_revision' => 1, 'visibility' => 'employee_shared', 'reason' => 'Former authority', 'content_review_confirmed' => true]))->toThrow(HttpException::class);
    });
    expect($document->fresh()->revision)->toBe(1);
});
it('preserves committed historical bytes when a post commit callback fails', function (): void {
    [$hr,$person,$existing] = committedHistoricalFixture();
    HistoricalDocument::created(function (): void {
        DB::afterCommit(function (): never {
            throw new RuntimeException('Deliberate historical callback failure after commit');
        });
    });
    try {
        expect(fn () => app(SaveHistoricalDocument::class)->handle($hr, $person, null, ['expected_revision' => 0, 'visibility' => 'hr_only', 'title' => 'Second archive', 'document_date' => '2025-02-01', 'source' => 'Fictional second reference', 'reason' => 'Initial'], UploadedFile::fake()->createWithContent('second.pdf', '%PDF-1.4
Fictional source')))->toThrow(RuntimeException::class, 'Deliberate historical callback failure');
    } finally {
        HistoricalDocument::flushEventListeners();
        HistoricalDocument::clearBootedModels();
    }
    $document = HistoricalDocument::query()->where('id', '!=', $existing->id)->sole();
    expect(Storage::disk('evidence')->exists($document->storage_path))->toBeTrue()->and($document->revision)->toBe(1);
});
