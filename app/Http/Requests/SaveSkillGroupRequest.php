<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\SaveSkillGroup;
use App\Capability;
use App\Models\SkillGroup;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SaveSkillGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->hasCapability(Capability::ManageFrameworks);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $record = $this->route('skill_group');

        return SaveSkillGroup::rules($record instanceof SkillGroup ? $record : null);
    }
}
