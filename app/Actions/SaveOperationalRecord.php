<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\Employee;
use App\Models\OperationalRecord;
use App\Models\User;
use App\OperationalMetric;
use App\Services\EmployeeAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveOperationalRecord
{
    public function __construct(private EmployeeAccess $access) {}

    /** @return array<string,array<int,mixed>> */
    public static function rules(): array
    {
        return ['metric' => ['required', Rule::enum(OperationalMetric::class)], 'unit' => ['required', Rule::in(['hours', 'observations', 'percent', 'narrative'])], 'value' => ['required_unless:metric,retention_context,role_criticality,human_context', 'nullable', 'numeric', 'between:0,999999.99', 'regex:/^\d{1,6}(?:\.\d{1,2})?$/'], 'period_start' => ['required', 'date_format:Y-m-d'], 'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'], 'source' => ['required', 'string', 'max:1000'], 'notes' => ['required_if:metric,retention_context,role_criticality,human_context', 'nullable', 'string', 'max:8000'], 'expected_sequence' => ['required', 'integer', 'min:0'], 'supersedes_id' => ['nullable', 'integer', 'min:1'], 'correction_reason' => ['required_with:supersedes_id', 'nullable', 'string', 'max:1000'], 'employee_id' => ['prohibited'], 'recorded_by' => ['prohibited'], 'is_interpretation' => ['prohibited'], 'value_hundredths' => ['prohibited']];
    }

    /** @param array<string,mixed> $input */
    public function handle(User $actor, Employee $employee, array $input): OperationalRecord
    {
        return DB::transaction(function () use ($actor, $employee, $input): OperationalRecord {
            $account = $this->access->currentAccount($actor);
            $person = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            Gate::forUser($account)->authorize('viewOperationalContext', $person);
            $values = Validator::make($input, self::rules())->validate();
            $metric = OperationalMetric::from($values['metric']);
            if ($metric->unit() !== $values['unit']) {
                throw ValidationException::withMessages(['unit' => 'This metric requires '.$metric->unit().'.']);
            }
            $value = null;
            if (isset($values['value'])) {
                $parts = explode('.', (string) $values['value']);
                $value = (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
            }
            if ($metric->isInterpretation() && $value !== null) {
                throw ValidationException::withMessages(['value' => 'Human interpretation uses narrative rather than a numeric penalty.']);
            }
            if ($metric === OperationalMetric::RightFirstTime && $value > 10000) {
                throw ValidationException::withMessages(['value' => 'A percentage must be between 0 and 100.']);
            }
            if ($metric === OperationalMetric::PpeObservations && $value !== null && $value % 100 !== 0) {
                throw ValidationException::withMessages(['value' => 'An observation count must be a whole number.']);
            }
            $records = OperationalRecord::query()->where('employee_id', $person->id)->orderBy('sequence')->lockForUpdate()->get();
            $sequence = $records->last()->sequence ?? 0;
            if ($sequence !== (int) $values['expected_sequence']) {
                throw ValidationException::withMessages(['expected_sequence' => 'Context history changed. Reload before recording this entry.']);
            }
            $supersedesId = isset($values['supersedes_id']) ? (int) $values['supersedes_id'] : null;
            $superseded = $records->pluck('supersedes_id')->filter()->all();
            if ($supersedesId !== null && (! $records->contains('id', $supersedesId) || in_array($supersedesId, $superseded, true))) {
                throw ValidationException::withMessages(['supersedes_id' => 'Choose a current entry for this employee.']);
            }
            $record = OperationalRecord::query()->create(['employee_id' => $person->id, 'sequence' => $sequence + 1, 'metric' => $metric, 'unit' => $metric->unit(), 'value_hundredths' => $value, 'is_interpretation' => $metric->isInterpretation(), 'period_start' => $values['period_start'], 'period_end' => $values['period_end'], 'source' => $values['source'], 'notes' => $values['notes'] ?? null, 'recorded_by' => $actor->id, 'supersedes_id' => $supersedesId, 'correction_reason' => $values['correction_reason'] ?? null]);
            AuditEvent::query()->create(['actor_id' => $actor->id, 'event' => 'context.recorded', 'subject_type' => 'operational_record', 'subject_id' => $record->id, 'details' => ['employee_id' => $person->id, 'sequence' => $record->sequence, 'metric' => $metric->value, 'supersedes_id' => $supersedesId]]);

            return $record;
        }, 3);
    }
}
