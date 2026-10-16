<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\JobRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveJobRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->canManageDirectory();
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => Str::upper(trim($this->input('code')))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'code' => $this->route('job_role') instanceof JobRole ? ['prohibited'] : ['required', 'string', 'max:40', 'regex:/\A[A-Z0-9][A-Z0-9._-]{0,39}\z/', Rule::unique('job_roles', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array{code: string, name: string, is_active: bool} */
    public function recordData(): array
    {
        return ['code' => $this->string('code')->toString(), 'name' => $this->string('name')->toString(), 'is_active' => $this->boolean('is_active')];
    }
}
