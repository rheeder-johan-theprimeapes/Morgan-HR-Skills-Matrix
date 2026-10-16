<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\SaveAssessment;
use App\AssessmentKind;
use App\Models\Assessment;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;

class SaveAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $kind = is_string($this->input('kind')) ? AssessmentKind::tryFrom($this->input('kind')) : null;
        $employee = $this->route('employee');

        return $kind !== null && $employee instanceof Employee && ($this->user()?->can('create', [Assessment::class, $employee, $kind]) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return SaveAssessment::rules();
    }
}
