<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DecidePdrSubmission;
use App\Http\Requests\DecidePdrSubmissionRequest;
use App\Models\PdrReview;
use App\Models\PdrSubmission;
use App\Models\User;
use App\PdrState;
use App\Services\PdrAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PdrDecisionController extends Controller
{
    public function index(Request $request, PdrAccess $access): View
    {
        $actor = $this->actor($request);
        abort_unless($access->hasDecisionAuthority($actor), 403);
        $reviews = $access->visible($actor)->where('state', PdrState::HrReviewed)->where('employee_id', '!=', $actor->employee_id)->where('manager_user_id', '!=', $actor->id)->with('currentSubmission')->orderBy('id')->paginate(25);
        $reviews->through(function (PdrReview $review): array {
            $submission = $review->currentSubmission;
            if ($submission === null) {
                abort(404);
            }
            $snapshot = $submission->snapshot;
            $proposal = $snapshot['pay_proposal'];
            if (is_array($proposal)) {
                unset($proposal['benchmark_id']);
            }

            return ['id' => $review->id, 'submission_id' => $submission->id, 'number' => $submission->submission_number, 'name' => $snapshot['person']['name'], 'employee_number' => $snapshot['person']['employee_number'], 'cycle' => $snapshot['cycle']['name'], 'score' => $snapshot['score']['score'], 'band' => $snapshot['score']['band'], 'pay' => $snapshot['actual_pay'], 'proposal' => $proposal];
        });

        return view('reviews.decisions', ['reviews' => $reviews]);
    }

    public function store(DecidePdrSubmissionRequest $request, PdrReview $review, PdrSubmission $submission, DecidePdrSubmission $decide): RedirectResponse
    {
        $decide->handle($this->actor($request), $review, $submission, $request->validated());

        return redirect()->route('submissions.show', [$review, $submission])->with('status', 'Human SLT decision recorded. Actual pay is unchanged.');
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
