<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AcknowledgePdrReview;
use App\Actions\RecordPdrFeedback;
use App\Actions\ReleasePdrReview;
use App\Http\Requests\AcknowledgePdrReviewRequest;
use App\Http\Requests\RecordPdrFeedbackRequest;
use App\Http\Requests\ReleasePdrReviewRequest;
use App\Models\PdrFeedback;
use App\Models\PdrReview;
use App\Models\User;
use App\PdrState;
use App\Services\EmployeeReviewSummary;
use App\Services\PdrAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PdrFeedbackController extends Controller
{
    public function edit(Request $request, PdrReview $review, PdrAccess $access, EmployeeReviewSummary $summaries): View
    {
        $actor = $this->actor($request);
        abort_unless($access->canRecordFeedback($actor, $review), 403);
        $feedback = PdrFeedback::query()->whereKey($review->current_feedback_id)->where('pdr_review_id', $review->id)->first();
        $page = ['id' => $review->id, 'name' => $review->employee->name ?? 'Employee unavailable', 'version' => $review->version, 'state' => $review->state->value, 'editable' => in_array($review->state, [PdrState::Approved, PdrState::FeedbackRecorded], true), 'is_manager' => $access->canManager($actor, $review), 'feedback' => $feedback === null ? null : ['revision' => $feedback->revision, 'held_on' => $feedback->held_on->toDateString(), 'summary' => $feedback->summary, 'development_message' => $feedback->development_message], 'can_view_outcome' => in_array($review->state, [PdrState::Released, PdrState::Acknowledged], true) && $summaries->canView($actor, $review)];

        return view('reviews.feedback', ['page' => $page]);
    }

    public function store(RecordPdrFeedbackRequest $request, PdrReview $review, RecordPdrFeedback $record): RedirectResponse
    {
        $record->handle($this->actor($request), $review, $request->validated());

        return redirect()->route('feedback.edit', $review)->with('status', 'Feedback revision recorded. Review it before explicit release.');
    }

    public function release(ReleasePdrReviewRequest $request, PdrReview $review, ReleasePdrReview $release): RedirectResponse
    {
        $release->handle($this->actor($request), $review, $request->validated());

        return redirect()->route('feedback.edit', $review)->with('status', 'Approved employee-safe outcome released.');
    }

    public function show(Request $request, PdrReview $review, EmployeeReviewSummary $summaries): View
    {
        return view('reviews.outcome', ['page' => $summaries->project($this->actor($request), $review)]);
    }

    public function acknowledge(AcknowledgePdrReviewRequest $request, PdrReview $review, AcknowledgePdrReview $record): RedirectResponse
    {
        $record->handle($this->actor($request), $review, $request->validated());

        return redirect()->route('outcomes.show', $review)->with('status', 'Receipt recorded. Agreement is not inferred.');
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
