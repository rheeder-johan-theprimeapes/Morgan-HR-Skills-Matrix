<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class LinkEmployeeAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $person = $this->route('employee');

        return $actor instanceof User && $person instanceof Employee && $actor->can('linkAccount', $person);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['account_id' => ['required', 'integer', 'exists:users,id']];
    }
}
