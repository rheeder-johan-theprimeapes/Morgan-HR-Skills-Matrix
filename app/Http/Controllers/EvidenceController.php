<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\EvidenceAttachment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenceController extends Controller
{
    public function show(Request $request, EvidenceAttachment $evidenceAttachment): StreamedResponse
    {
        $assessment = $evidenceAttachment->revision?->assessment;
        if ($assessment === null) {
            abort(404);
        }
        Gate::authorize('view', $assessment);
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $disk = Storage::disk('evidence');
        if (! $disk->exists($evidenceAttachment->storage_path)) {
            abort(404, 'Evidence is unavailable. Contact HR.');
        }
        $contents = $disk->get($evidenceAttachment->storage_path);
        if (! is_string($contents) || ! hash_equals($evidenceAttachment->sha256, hash('sha256', $contents))) {
            AuditEvent::query()->create(['actor_id' => $actor->id, 'event' => 'evidence.integrity_failed', 'subject_type' => 'evidence_attachment', 'subject_id' => $evidenceAttachment->id, 'details' => []]);
            abort(409, 'Evidence integrity could not be confirmed. Contact HR.');
        }
        AuditEvent::query()->create(['actor_id' => $actor->id, 'event' => 'evidence.downloaded', 'subject_type' => 'evidence_attachment', 'subject_id' => $evidenceAttachment->id, 'details' => ['assessment_revision_id' => $evidenceAttachment->assessment_revision_id]]);

        return $disk->download($evidenceAttachment->storage_path, $evidenceAttachment->original_name, ['X-Content-Type-Options' => 'nosniff', 'Content-Type' => 'application/octet-stream', 'Cache-Control' => 'private, no-store']);
    }
}
