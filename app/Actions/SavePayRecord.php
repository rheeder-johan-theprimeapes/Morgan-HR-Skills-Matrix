<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\Employee;
use App\Models\PayRecord;
use App\Models\User;
use App\Services\EmployeeAccess;
use App\Services\PayTerms;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SavePayRecord
{
    public function __construct(private EmployeeAccess $access) {}

    /** @return array<string,array<int,mixed>> */
    public static function rules(): array
    {
        return PayTerms::rules() + ['expected_sequence' => ['required', 'integer', 'min:0'], 'effective_on' => ['required', 'date_format:Y-m-d']];
    }

    /** @param array<string,mixed> $input */
    public function handle(User $actor, Employee $employee, array $input): PayRecord
    {
        return DB::transaction(function () use ($actor, $employee, $input): PayRecord {
            $account = $this->access->currentAccount($actor);
            $person = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            Gate::forUser($account)->authorize('viewPay', $person);
            $values = Validator::make($input, self::rules())->validate();
            $records = PayRecord::query()->where('employee_id', $person->id)->orderBy('sequence')->lockForUpdate()->get();
            $sequence = $records->last()->sequence ?? 0;
            if ($sequence !== (int) $values['expected_sequence']) {
                throw ValidationException::withMessages(['expected_sequence' => 'Pay history changed. Reload before recording this entry.']);
            }
            $attributes = PayTerms::attributes($values);
            $supersededIds = $records->pluck('supersedes_id')->filter()->all();
            $previous = $attributes['supersedes_id'] === null ? null : $records->firstWhere('id', $attributes['supersedes_id']);
            if ($attributes['supersedes_id'] !== null && ($previous === null || in_array($previous->id, $supersededIds, true))) {
                throw ValidationException::withMessages(['supersedes_id' => 'Choose a current entry for this employee.']);
            }
            if ($records->contains(fn (PayRecord $record): bool => $record->id !== $attributes['supersedes_id'] && ! in_array($record->id, $supersededIds, true) && $record->effective_on->toDateString() === $values['effective_on'])) {
                throw ValidationException::withMessages(['effective_on' => 'An entry already exists for this date. Record an explicit correction.']);
            }
            $record = PayRecord::query()->create($attributes + ['employee_id' => $person->id, 'sequence' => $sequence + 1, 'effective_on' => $values['effective_on'], 'recorded_by' => $actor->id]);
            AuditEvent::query()->create(['actor_id' => $actor->id, 'event' => 'pay.recorded', 'subject_type' => 'pay_record', 'subject_id' => $record->id, 'details' => ['employee_id' => $person->id, 'sequence' => $record->sequence, 'supersedes_id' => $record->supersedes_id]]);

            return $record;
        }, 3);
    }
}
