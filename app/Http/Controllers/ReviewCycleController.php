<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AllocatePdrReview;
use App\Actions\SaveReviewCycle;
use App\Http\Requests\AllocatePdrReviewRequest;
use App\Http\Requests\SaveReviewCycleRequest;
use App\Models\PdrReview;
use App\Models\ReviewCycle;
use App\Models\ScoringFramework;
use App\Models\User;
use App\Services\EmployeeAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReviewCycleController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-review-cycles');

        return view('reviews.cycles', ['cycles' => ReviewCycle::query()->with('framework')->latest('id')->paginate(20), 'frameworks' => ScoringFramework::query()->whereNotNull('published_at')->latest('id')->get(['id', 'name', 'illustrative'])]);
    }

    public function store(SaveReviewCycleRequest $request, SaveReviewCycle $save): RedirectResponse
    {
        $cycle = $save->handle($this->actor($request), null, $request->validated());

        return redirect()->route('review-cycles.show', $cycle)->with('status', 'Review cycle created.');
    }

    public function show(Request $request, ReviewCycle $reviewCycle, EmployeeAccess $access): View
    {
        Gate::authorize('manage-review-cycles');
        $people = $access->query($this->actor($request))->orderBy('name')->get(['id', 'name', 'employee_number', 'is_active']);
        $ids = $people->modelKeys();
        $reviews = $reviewCycle->reviews()->whereIn('employee_id', $ids)->with(['employee', 'manager'])->orderBy('id')->paginate(25);
        $reviews->through(fn (PdrReview $review): array => ['id' => $review->id, 'employee_id' => $review->employee_id, 'name' => $review->employee->name ?? 'Employee unavailable', 'manager_name' => $review->manager->name ?? 'Manager unavailable', 'manager_user_id' => $review->manager_user_id, 'section_profile' => $review->section_profile, 'state' => $review->state->value, 'version' => $review->version]);

        return view('reviews.cycle', ['cycle' => $reviewCycle->load('framework'), 'people' => $people, 'managers' => User::query()->whereIn('employee_id', $ids)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'employee_id']), 'reviews' => $reviews]);
    }

    public function update(SaveReviewCycleRequest $request, ReviewCycle $reviewCycle, SaveReviewCycle $save): RedirectResponse
    {
        $save->handle($this->actor($request), $reviewCycle, $request->validated());

        return redirect()->route('review-cycles.show', $reviewCycle)->with('status', 'Cycle dates/settings saved.');
    }

    public function allocate(AllocatePdrReviewRequest $request, ReviewCycle $reviewCycle, AllocatePdrReview $allocate): RedirectResponse
    {
        $allocate->handle($this->actor($request), $reviewCycle, $request->validated());

        return redirect()->route('review-cycles.show', $reviewCycle)->with('status', 'Review allocated.');
    }

    public function reassign(AllocatePdrReviewRequest $request, PdrReview $review, AllocatePdrReview $allocate): RedirectResponse
    {
        $cycle = $review->cycle;
        if ($cycle === null) {
            abort(404);
        }
        $allocate->handle($this->actor($request), $cycle, $request->validated(), $review);

        return redirect()->route('review-cycles.show', $review->review_cycle_id)->with('status', 'Draft allocation reassigned with an audit reason.');
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
