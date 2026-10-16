<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ReviewPdrSubmission;
use App\Actions\SubmitPdrReview;
use App\Http\Requests\ReviewPdrSubmissionRequest;
use App\Http\Requests\SubmitPdrReviewRequest;
use App\Models\AuditEvent;
use App\Models\EvidenceAttachment;
use App\Models\PdrReview;
use App\Models\PdrSubmission;
use App\Models\User;
use App\Services\EvidenceAvailability;
use App\Services\PdrSnapshot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdrSubmissionController extends Controller
{
    public function submit(SubmitPdrReviewRequest $request, PdrReview $review, SubmitPdrReview $submit): RedirectResponse
    {
        $submission = $submit->handle($this->actor($request), $review, $request->validated());

        return redirect()->route('submissions.show', [$review, $submission])->with('status', 'Review submitted. This revision is frozen.');
    }

    public function show(Request $request, PdrReview $review, PdrSubmission $submission, PdrSnapshot $snapshots): View
    {
        return view('reviews.submission', ['page' => $snapshots->project($this->actor($request), $review, $submission)]);
    }

    public function hr(ReviewPdrSubmissionRequest $request, PdrReview $review, PdrSubmission $submission, ReviewPdrSubmission $reviewSubmission): RedirectResponse
    {
        $reviewSubmission->handle($this->actor($request), $review, $submission, $request->validated());

        return redirect()->route('reviews.show', $review)->with('status', 'HR review transition recorded.');
    }

    public function evidence(Request $request, PdrReview $review, PdrSubmission $submission, int $evidence, PdrSnapshot $snapshots, EvidenceAvailability $availability): StreamedResponse
    {
        $actor = $this->actor($request);
        $snapshots->authorize($actor, $review, $submission);
        $file = null;
        foreach ($submission->snapshot['assessments'] as $assessment) {
            foreach ($assessment['attachments'] as $attachment) {
                if ($attachment['id'] === $evidence) {
                    $file = $attachment;
                }
            }
        }
        abort_unless(is_array($file) && $file['available'], 404);
        $attachment = new EvidenceAttachment($file);
        abort_unless($availability->isIntact($attachment), 404, 'This frozen evidence copy is unavailable.');
        AuditEvent::query()->create(['actor_id' => $actor->id, 'event' => 'review.evidence.downloaded', 'subject_type' => 'pdr_submission', 'subject_id' => $submission->id, 'details' => ['evidence_id' => $evidence]]);

        return Storage::disk('evidence')->download($file['storage_path'], $file['original_name'], ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Type' => 'application/octet-stream']);
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
