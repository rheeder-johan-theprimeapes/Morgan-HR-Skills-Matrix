<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\SaveSkillTarget;
use App\Capability;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SaveSkillTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->hasCapability(Capability::ManageFrameworks);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return SaveSkillTarget::rules();
    }
}
