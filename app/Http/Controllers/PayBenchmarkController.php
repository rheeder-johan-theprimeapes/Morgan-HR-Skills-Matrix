<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SavePayBenchmark;
use App\Http\Requests\SavePayBenchmarkRequest;
use App\Models\JobRole;
use App\Models\PayBenchmark;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PayBenchmarkController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manage-benchmarks');
        $values = $request->validate(['correct' => ['nullable', 'integer', 'min:1']]);
        $correction = isset($values['correct']) ? PayBenchmark::query()->with('correction')->whereKey($values['correct'])->firstOrFail() : null;

        return view('pay.benchmarks', ['records' => PayBenchmark::query()->with('correction')->latest('id')->paginate(20), 'roles' => JobRole::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']), 'correction' => $correction]);
    }

    public function store(SavePayBenchmarkRequest $request, SavePayBenchmark $save): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $save->handle($actor, $request->validated());

        return redirect()->route('benchmarks.index')->with('status', 'Benchmark source recorded; previous entries preserved.');
    }
}
