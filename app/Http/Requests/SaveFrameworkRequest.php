<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\SaveFramework;
use App\Capability;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SaveFrameworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->hasCapability(Capability::ManageFrameworks);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['components' => $this->input('components', []), 'bands' => $this->input('bands', [])]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['form_complete' => ['required', 'accepted']] + SaveFramework::rules();
    }
}
