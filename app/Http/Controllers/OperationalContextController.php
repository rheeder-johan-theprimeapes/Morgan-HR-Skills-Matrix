<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SaveOperationalRecord;
use App\Http\Requests\SaveOperationalRecordRequest;
use App\Models\Employee;
use App\Models\OperationalRecord;
use App\Models\User;
use App\Services\EmployeeProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OperationalContextController extends Controller
{
    public function index(Request $request, Employee $employee): View
    {
        Gate::authorize('viewOperationalContext', $employee);
        $values = $request->validate(['from' => ['required_with:to', 'nullable', 'date_format:Y-m-d'], 'to' => ['required_with:from', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'], 'correct' => ['nullable', 'integer', 'min:1']]);
        $query = OperationalRecord::query()->where('employee_id', $employee->id);
        $sequence = (clone $query)->max('sequence') ?? 0;
        $current = (clone $query)->whereDoesntHave('correction')->get(['id', 'metric', 'unit', 'period_start', 'period_end']);
        $overlaps = [];
        foreach ($current as $left) {
            foreach ($current as $right) {
                if ($left->id < $right->id && $left->metric === $right->metric && $left->unit === $right->unit && $left->period_start <= $right->period_end && $left->period_end >= $right->period_start) {
                    $overlaps[$left->id][] = $right->id;
                    $overlaps[$right->id][] = $left->id;
                }
            }
        }
        $correction = isset($values['correct']) ? (clone $query)->with('correction')->whereKey($values['correct'])->firstOrFail() : null;
        if (isset($values['from'], $values['to'])) {
            $query->whereDate('period_start', '<=', $values['to'])->whereDate('period_end', '>=', $values['from']);
        }

        return view('context.index', ['profile' => EmployeeProfile::fromEmployee($employee), 'records' => $query->with('correction')->orderByDesc('sequence')->paginate(25)->withQueryString(), 'sequence' => $sequence, 'correction' => $correction, 'from' => $values['from'] ?? null, 'to' => $values['to'] ?? null, 'overlaps' => $overlaps]);
    }

    public function store(SaveOperationalRecordRequest $request, Employee $employee, SaveOperationalRecord $save): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $save->handle($actor, $employee, $request->validated());

        return redirect()->route('context.index', $employee)->with('status', 'Source evidence recorded; earlier context preserved.');
    }
}
