<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class SignInRequest extends FormRequest
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

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['login_id' => ['required', 'string', 'max:40'], 'password' => ['required', 'string', 'max:256']];
    }
}
