<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->canManageDirectory();
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('employee_number'))) {
            $this->merge(['employee_number' => Str::upper(trim($this->input('employee_number')))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'employee_number' => $this->route('employee') instanceof Employee ? ['prohibited'] : ['required', 'string', 'max:40', 'regex:/\A[A-Z0-9][A-Z0-9._-]{0,39}\z/', Rule::unique('employees', 'employee_number')],
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'job_role_id' => ['nullable', 'integer', Rule::exists('job_roles', 'id')],
            'manager_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array{employee_number: string, name: string, department_id: int|null, job_role_id: int|null, manager_id: int|null, is_active: bool} */
    public function employeeData(): array
    {
        return [
            'employee_number' => $this->string('employee_number')->toString(), 'name' => $this->string('name')->toString(),
            'department_id' => $this->filled('department_id') ? $this->integer('department_id') : null,
            'job_role_id' => $this->filled('job_role_id') ? $this->integer('job_role_id') : null,
            'manager_id' => $this->filled('manager_id') ? $this->integer('manager_id') : null,
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
