<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\SavePayRecord;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SavePayRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $person = $this->route('employee');

        return $this->user() instanceof User && $person instanceof Employee && Gate::allows('viewPay', $person);
    }

    /** @return array<string,array<int,mixed>> */
    public function rules(): array
    {
        return SavePayRecord::rules();
    }
}
