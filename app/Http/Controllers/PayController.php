<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SavePayRecord;
use App\Http\Requests\SavePayRecordRequest;
use App\Models\Employee;
use App\Models\PayBenchmark;
use App\Models\PayRecord;
use App\Models\User;
use App\Services\EmployeeProfile;
use App\Services\PayComparison;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PayController extends Controller
{
    public function index(Request $request, Employee $employee): View
    {
        Gate::authorize('viewPay', $employee);
        $values = $request->validate(['correct' => ['nullable', 'integer', 'min:1']]);
        $records = PayRecord::query()->where('employee_id', $employee->id)->with('correction')->orderByDesc('sequence')->get();
        $correction = isset($values['correct']) ? $records->firstWhere('id', (int) $values['correct']) : null;
        abort_if(isset($values['correct']) && $correction === null, 404);

        return view('pay.index', ['profile' => EmployeeProfile::fromEmployee($employee), 'records' => $records, 'sequence' => $records->first()->sequence ?? 0, 'correction' => $correction]);
    }

    public function store(SavePayRecordRequest $request, Employee $employee, SavePayRecord $save): RedirectResponse
    {
        $save->handle($this->actor($request), $employee, $request->validated());

        return redirect()->route('pay.index', $employee)->with('status', 'Pay evidence recorded; previous entries preserved.');
    }

    public function comparison(Request $request, Employee $employee, PayComparison $comparisons): View
    {
        Gate::authorize('viewPay', $employee);
        Gate::authorize('manage-benchmarks');
        $values = $request->validate(['as_of' => ['nullable', 'date_format:Y-m-d'], 'benchmark_id' => ['nullable', 'integer', 'min:1']]);
        $asOf = $values['as_of'] ?? now()->toDateString();
        $actual = PayRecord::query()->where('employee_id', $employee->id)->whereDate('effective_on', '<=', $asOf)->whereDoesntHave('correction')->orderByDesc('effective_on')->first();
        $benchmark = isset($values['benchmark_id']) ? PayBenchmark::query()->whereKey($values['benchmark_id'])->firstOrFail() : null;

        return view('pay.comparison', ['profile' => EmployeeProfile::fromEmployee($employee), 'actual' => $actual, 'benchmark' => $benchmark, 'asOf' => $asOf, 'benchmarks' => PayBenchmark::query()->whereDoesntHave('correction')->orderBy('title')->get(['id', 'title', 'basis', 'currency']), 'comparison' => $benchmark === null ? null : $comparisons->compare($employee, $actual, $benchmark, $asOf)]);
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
