<?php

declare(strict_types=1);

namespace App\Actions;

use App\DocumentVisibility;
use App\Models\AuditEvent;
use App\Models\DocumentVisibilityRevision;
use App\Models\Employee;
use App\Models\HistoricalDocument;
use App\Models\User;
use App\Services\EmployeeAccess;
use App\Services\EvidenceAvailability;
use App\Services\HistoricalDocumentAccess;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;
use Throwable;

class SaveHistoricalDocument
{
    public function __construct(private EmployeeAccess $people, private HistoricalDocumentAccess $access, private EvidenceAvailability $evidence) {}

    /** @return array<string,array<int,mixed>> */
    public static function rules(bool $creating): array
    {
        $rules = ['expected_revision' => ['required', 'integer', 'min:0'], 'visibility' => ['required', Rule::enum(DocumentVisibility::class)], 'reason' => ['required', 'string', 'max:4000'], 'content_review_confirmed' => ['required_unless:visibility,hr_only', 'boolean', 'accepted_if:visibility,supervisors,employee_shared'], 'employee_id' => ['prohibited'], 'uploaded_by' => ['prohibited'], 'storage_path' => ['prohibited'], 'sha256' => ['prohibited'], 'revision' => ['prohibited'], 'actor_id' => ['prohibited']];
        $rules['title'] = $creating ? ['required', 'string', 'max:180'] : ['prohibited'];
        $rules['document_date'] = $creating ? ['required', 'date_format:Y-m-d', 'before_or_equal:today'] : ['prohibited'];
        $rules['source'] = $creating ? ['required', 'string', 'max:4000'] : ['prohibited'];
        $rules['attachment'] = $creating ? ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:10240'] : ['prohibited'];

        return $rules;
    }

    /** @param array<string,mixed> $input */
    public function handle(User $actor, Employee $employee, ?HistoricalDocument $document, array $input, ?UploadedFile $attachment = null): HistoricalDocument
    {
        abort_unless($this->access->canAdminister($actor, $employee), 403);
        $input['attachment'] = $attachment;
        $values = Validator::make($input, self::rules($document === null))->validate();
        $storedPath = null;
        $metadata = [];
        try {
            if ($attachment !== null) {
                $original = $attachment->getClientOriginalName();
                if (preg_match('/[\x00-\x1F\x7F]/', $original) === 1 || str_contains($original, '/') || str_contains($original, chr(92)) || strlen($original) > 180 || str_starts_with($original, '.')) {
                    throw ValidationException::withMessages(['attachment' => 'Use a short filename without paths, leading dots or control characters.']);
                }
                $name = trim(preg_replace('/[^A-Za-z0-9._ -]/', '_', Str::ascii($original)) ?? 'history.pdf', '. ');
                $hash = hash_file('sha256', $attachment->getPathname());
                $size = $attachment->getSize();
                if (! is_string($hash) || ! is_int($size)) {
                    throw ValidationException::withMessages(['attachment' => 'The historical file could not be inspected.']);
                }
                $filename = $attachment->hashName();
                $storedPath = 'historical/'.$filename;
                try {
                    $stored = Storage::disk('evidence')->putFileAs('historical', $attachment, $filename);
                } catch (FilesystemException $failure) {
                    report($failure);
                    throw ValidationException::withMessages(['attachment' => 'The historical file could not be stored. Retry.']);
                }
                if (! is_string($stored) || ! $this->evidence->isIntactFile($storedPath, $size, $hash)) {
                    throw ValidationException::withMessages(['attachment' => 'The stored historical file could not be verified. Retry.']);
                }
                $metadata = ['storage_path' => $storedPath, 'original_name' => $name, 'mime_type' => $attachment->getMimeType() ?? 'application/octet-stream', 'byte_size' => $size, 'sha256' => $hash];
            }

            return DB::transaction(function () use ($actor, $employee, $document, $values, $metadata): HistoricalDocument {
                $account = $this->people->currentAccount($actor);
                if ($account === null) {
                    abort(403);
                }
                $person = Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
                abort_unless($this->access->canAdminister($account, $person), 403);
                $head = $document === null ? null : HistoricalDocument::query()->whereKey($document->id)->where('employee_id', $person->id)->lockForUpdate()->firstOrFail();
                if (($head->revision ?? 0) !== (int) $values['expected_revision']) {
                    throw ValidationException::withMessages(['expected_revision' => 'Visibility changed. Your input is retained; reload before saving again.']);
                }
                $revision = ($head->revision ?? 0) + 1;
                if ($head === null) {
                    $head = HistoricalDocument::query()->create($metadata + ['employee_id' => $person->id, 'uploaded_by' => $account->id, 'title' => $values['title'], 'document_date' => $values['document_date'], 'source' => $values['source'], 'visibility' => $values['visibility'], 'revision' => $revision]);
                } else {
                    $head->update(['visibility' => $values['visibility'], 'revision' => $revision]);
                }
                DocumentVisibilityRevision::query()->create(['historical_document_id' => $head->id, 'revision' => $revision, 'visibility' => $values['visibility'], 'content_review_confirmed' => (bool) ($values['content_review_confirmed'] ?? false), 'reason' => $values['reason'], 'actor_id' => $account->id]);
                AuditEvent::query()->create(['actor_id' => $account->id, 'event' => $document === null ? 'historical_document.uploaded' : 'historical_document.visibility_changed', 'subject_type' => 'historical_document', 'subject_id' => $head->id, 'details' => ['employee_id' => $person->id, 'revision' => $revision, 'visibility' => $values['visibility']]]);

                return $head;
            }, 3);
        } catch (Throwable $failure) {
            if ($storedPath !== null) {
                try {
                    if (! HistoricalDocument::query()->where('storage_path', $storedPath)->exists()) {
                        Storage::disk('evidence')->delete($storedPath);
                    }
                } catch (Throwable $cleanupFailure) {
                    report($cleanupFailure);
                }
            }
            throw $failure;
        }
    }
}
