<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Validation\Rule;

class PayTerms
{
    public const CURRENCIES = ['GBP', 'EUR', 'USD', 'ZAR'];

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return ['amount' => ['required', 'numeric', 'between:0.01,999999999.99', 'regex:/^\d{1,9}(?:\.\d{1,2})?$/'], 'basis' => ['required', Rule::in(['annual', 'hourly'])], 'currency' => ['required', Rule::in(self::CURRENCIES)],
            'weekly_hours' => ['required_with:weeks_per_year', 'nullable', 'numeric', 'between:0.01,168', 'regex:/^\d{1,3}(?:\.\d{1,2})?$/'], 'weeks_per_year' => ['required_with:weekly_hours', 'nullable', 'numeric', 'between:0.01,53', 'regex:/^\d{1,2}(?:\.\d{1,2})?$/'],
            'source' => ['required', 'string', 'max:1000'], 'supersedes_id' => ['nullable', 'integer', 'min:1'], 'correction_reason' => ['required_with:supersedes_id', 'nullable', 'string', 'max:1000'], 'recorded_by' => ['prohibited'], 'employee_id' => ['prohibited'], 'amount_minor' => ['prohibited'], 'sequence' => ['prohibited']];
    }

    /**
     * @param  array<string,mixed>  $values
     * @return array{amount_minor:int,basis:string,currency:string,weekly_hours_hundredths:?int,weeks_per_year_hundredths:?int,source:string,supersedes_id:?int,correction_reason:?string}
     */
    public static function attributes(array $values): array
    {
        return ['amount_minor' => self::hundredths((string) $values['amount']), 'basis' => $values['basis'], 'currency' => $values['currency'], 'weekly_hours_hundredths' => isset($values['weekly_hours']) ? self::hundredths((string) $values['weekly_hours']) : null, 'weeks_per_year_hundredths' => isset($values['weeks_per_year']) ? self::hundredths((string) $values['weeks_per_year']) : null, 'source' => $values['source'], 'supersedes_id' => isset($values['supersedes_id']) ? (int) $values['supersedes_id'] : null, 'correction_reason' => $values['correction_reason'] ?? null];
    }

    public static function hundredths(string $value): int
    {
        $parts = explode('.', $value);

        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }
}
