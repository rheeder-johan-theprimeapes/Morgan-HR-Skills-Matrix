<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\SaveOperationalRecord;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveOperationalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $person = $this->route('employee');

        return $person instanceof Employee && Gate::allows('viewOperationalContext', $person);
    }

    /** @return array<string,array<int,mixed>> */
    public function rules(): array
    {
        return SaveOperationalRecord::rules();
    }
}
