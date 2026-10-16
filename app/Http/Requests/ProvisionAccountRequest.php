<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProvisionAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->user();

        return $account instanceof User && $account->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('employee_number'))) {
            $this->merge(['employee_number' => Str::upper(trim($this->input('employee_number')))]);
        }
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'employee_number' => ['required', 'string', 'max:40', 'regex:/\A[A-Z0-9][A-Z0-9._-]{0,39}\z/', Rule::unique('users', 'login_id'), Rule::unique('employees', 'employee_number')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
        ];
    }
}
