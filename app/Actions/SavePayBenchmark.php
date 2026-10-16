<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\JobRole;
use App\Models\PayBenchmark;
use App\Models\User;
use App\Services\EmployeeAccess;
use App\Services\PayTerms;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SavePayBenchmark
{
    public function __construct(private EmployeeAccess $access) {}

    /** @return array<string,array<int,mixed>> */
    public static function rules(): array
    {
        return PayTerms::rules() + ['job_role_id' => ['required', 'integer'], 'title' => ['required', 'string', 'max:180'], 'company_context' => ['required', 'string', 'max:2000'], 'duties' => ['required', 'string', 'max:4000'], 'limitations' => ['required', 'string', 'max:4000'], 'source_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'valid_from' => ['required', 'date_format:Y-m-d'], 'valid_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from']];
    }

    /** @param array<string,mixed> $input */
    public function handle(User $actor, array $input): PayBenchmark
    {
        try {
            return DB::transaction(function () use ($actor, $input): PayBenchmark {
                Gate::forUser($this->access->currentAccount($actor))->authorize('manage-benchmarks');
                $values = Validator::make($input, self::rules())->validate();
                $role = JobRole::query()->whereKey($values['job_role_id'])->lockForUpdate()->first();
                if ($role === null || ! $role->is_active) {
                    throw ValidationException::withMessages(['job_role_id' => 'Choose an active job role.']);
                }
                $attributes = PayTerms::attributes($values);
                if ($attributes['supersedes_id'] !== null) {
                    $previous = PayBenchmark::query()->whereKey($attributes['supersedes_id'])->lockForUpdate()->first();
                    if ($previous === null || PayBenchmark::query()->where('supersedes_id', $previous->id)->lockForUpdate()->first(['id']) !== null) {
                        throw ValidationException::withMessages(['supersedes_id' => 'This comparator was already corrected. Reload its history.']);
                    }
                }
                $record = PayBenchmark::query()->create($attributes + ['recorded_by' => $actor->id, 'job_role_id' => $role->id, 'title' => $values['title'], 'company_context' => $values['company_context'], 'duties' => $values['duties'], 'limitations' => $values['limitations'], 'source_date' => $values['source_date'], 'valid_from' => $values['valid_from'], 'valid_to' => $values['valid_to'] ?? null]);
                AuditEvent::query()->create(['actor_id' => $actor->id, 'event' => 'benchmark.recorded', 'subject_type' => 'pay_benchmark', 'subject_id' => $record->id, 'details' => ['job_role_id' => $role->id, 'supersedes_id' => $record->supersedes_id]]);

                return $record;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['supersedes_id' => 'This comparator was already corrected. Reload its history.']);
        }
    }
}
