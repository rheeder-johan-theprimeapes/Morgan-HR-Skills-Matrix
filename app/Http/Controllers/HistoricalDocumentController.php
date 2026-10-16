<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SaveHistoricalDocument;
use App\Http\Requests\SaveHistoricalDocumentRequest;
use App\Models\AuditEvent;
use App\Models\DocumentVisibilityRevision;
use App\Models\Employee;
use App\Models\HistoricalDocument;
use App\Models\User;
use App\Services\EvidenceAvailability;
use App\Services\HistoricalDocumentAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HistoricalDocumentController extends Controller
{
    public function index(Request $request, Employee $employee, HistoricalDocumentAccess $access): View
    {
        $actor = $this->actor($request);
        abort_unless($access->canList($actor, $employee), 403);
        $documents = $access->query($actor, $employee)->latest('id')->paginate(25)->through(fn (HistoricalDocument $row): array => $access->project($row));

        return view('historical-documents.index', ['person' => ['id' => $employee->id, 'name' => $employee->name], 'documents' => $documents, 'canAdminister' => $access->canAdminister($actor, $employee)]);
    }

    public function show(Request $request, HistoricalDocument $historicalDocument, HistoricalDocumentAccess $access): View
    {
        $actor = $this->actor($request);
        abort_unless($access->canView($actor, $historicalDocument), 403);
        $person = $historicalDocument->employee;
        if ($person === null) {
            abort(404);
        }
        $admin = $access->canAdminister($actor, $person);
        $history = $admin ? $historicalDocument->visibilityRevisions()->with('actor')->paginate(20)->through(fn (DocumentVisibilityRevision $row): array => ['revision' => $row->revision, 'visibility' => $row->visibility->label(), 'reason' => $row->reason, 'reviewed' => $row->content_review_confirmed, 'actor' => $row->actor->name ?? 'Unavailable account', 'recorded_at' => $row->created_at?->toDateTimeString()]) : null;

        return view('historical-documents.show', ['document' => $access->project($historicalDocument), 'person' => ['id' => $person->id, 'name' => $person->name], 'canAdminister' => $admin, 'history' => $history]);
    }

    public function store(SaveHistoricalDocumentRequest $request, Employee $employee, SaveHistoricalDocument $save): RedirectResponse
    {
        $file = $request->file('attachment');
        $document = $save->handle($this->actor($request), $employee, null, $request->validated(), $file instanceof UploadedFile ? $file : null);

        return redirect()->route('historical-documents.show', $document)->with('status', 'Historical document stored privately.');
    }

    public function visibility(SaveHistoricalDocumentRequest $request, HistoricalDocument $historicalDocument, SaveHistoricalDocument $save): RedirectResponse
    {
        $person = $historicalDocument->employee;
        if ($person === null) {
            abort(404);
        }
        $save->handle($this->actor($request), $person, $historicalDocument, $request->validated());

        return redirect()->route('historical-documents.show', $historicalDocument)->with('status', 'Visibility revision saved.');
    }

    public function download(Request $request, HistoricalDocument $historicalDocument, HistoricalDocumentAccess $access, EvidenceAvailability $evidence): StreamedResponse
    {
        $actor = $this->actor($request);
        abort_unless($access->canView($actor, $historicalDocument), 403);
        $path = $historicalDocument->storage_path;
        abort_unless(Storage::disk('evidence')->exists($path), 404, 'The historical file is unavailable. Contact HR.');
        abort_unless($evidence->isIntactFile($path, $historicalDocument->byte_size, $historicalDocument->sha256), 409, 'Historical file integrity could not be confirmed. Contact HR.');
        AuditEvent::query()->create(['actor_id' => $actor->id, 'event' => 'historical_document.downloaded', 'subject_type' => 'historical_document', 'subject_id' => $historicalDocument->id, 'details' => ['employee_id' => $historicalDocument->employee_id, 'revision' => $historicalDocument->revision]]);

        return Storage::disk('evidence')->download($path, $historicalDocument->original_name, ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

return $actor;
    }
}
