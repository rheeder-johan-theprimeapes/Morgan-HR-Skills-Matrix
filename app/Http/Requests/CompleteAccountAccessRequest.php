<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CompleteAccountAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('login_id'))) {
            $this->merge(['login_id' => Str::upper(trim($this->input('login_id')))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'login_id' => ['required', 'string', 'max:40'],
            'code' => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', 'confirmed', Password::min(12),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && (strlen($value) > 72 || str_contains($value, "\0"))) {
                        $fail('Use a shorter password without control characters.');
                    }
                },
            ],
        ];
    }
}
