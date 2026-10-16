<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Services\SkillsMatrix;
use Illuminate\Foundation\Http\FormRequest;

class SkillsMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->hasNativeAccess();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return SkillsMatrix::rules();
    }
}
