<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SavePdrDraft;
use App\Capability;
use App\Http\Requests\SavePdrDraftRequest;
use App\Models\PayBenchmark;
use App\Models\PdrReview;
use App\Models\User;
use App\Services\EmployeeScore;
use App\Services\PdrAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PdrReviewController extends Controller
{
    public function index(Request $request, PdrAccess $access): View
    {
        Gate::authorize('viewAny', PdrReview::class);
        $reviews = $access->visible($this->actor($request))->with(['cycle', 'employee'])->latest('id')->paginate(25);
        $reviews->through(fn (PdrReview $review): array => ['id' => $review->id, 'name' => $review->employee->name ?? 'Employee unavailable', 'cycle' => $review->cycle->name ?? 'Cycle unavailable', 'state' => $review->state->value, 'profile' => $review->section_profile]);

        return view('reviews.index', ['reviews' => $reviews]);
    }

    public function show(Request $request, PdrReview $review, PdrAccess $access, EmployeeScore $scores): View|RedirectResponse
    {
        $actor = $this->actor($request);
        if (! $access->canEmployee($actor, $review) && ! $access->canManager($actor, $review) && ! $access->canAdminister($actor, $review) && $access->canInspectDecision($actor, $review)) {
            abort_if($review->current_submission_id === null, 404);

            return redirect()->route('submissions.show', [$review, $review->current_submission_id]);
        }
        $page = $access->projectDraft($actor, $review);
        $person = $review->employee;
        $cycle = $review->cycle;
        $framework = $cycle->framework ?? null;
        if ($person === null || $cycle === null || $framework === null) {
            abort(404);
        }
        $preview = $page['mode'] === 'manager' || $access->canAdminister($actor, $review) ? $scores->preview($actor, $person, $framework) : null;
        $benchmarks = $page['can_propose'] && $actor->hasCapability(Capability::Benchmarks) ? PayBenchmark::query()->where('job_role_id', $person->job_role_id)->whereDoesntHave('correction')->orderBy('title')->get(['id', 'title'])->map(fn (PayBenchmark $benchmark): array => ['id' => $benchmark->id, 'title' => $benchmark->title])->all() : [];

        $history = $review->submissions()->get(['id', 'submission_number', 'created_at'])->map(fn ($row): array => ['id' => $row->id, 'number' => $row->submission_number, 'created_at' => $row->created_at?->toDateTimeString()])->all();

        return view('reviews.show', ['history' => $page['mode'] === 'employee' ? [] : $history, 'page' => $page, 'preview' => $preview, 'benchmarks' => $benchmarks]);
    }

    public function save(SavePdrDraftRequest $request, PdrReview $review, SavePdrDraft $save): JsonResponse|RedirectResponse
    {
        $draft = $save->handle($this->actor($request), $review, $request->validated());
        if ($request->expectsJson()) {
            return response()->json(['status' => 'saved', 'revision' => $draft->revision]);
        }

        return redirect()->route('reviews.show', $review)->with('status', 'Draft saved.');
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
