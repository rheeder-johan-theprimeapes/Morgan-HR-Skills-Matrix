<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\PayBenchmark;
use App\Models\PayRecord;

class PayComparison
{
    public function convert(int $amountMinor, string $from, string $to, ?int $hoursHundredths, ?int $weeksHundredths): ?int
    {
        if (! in_array($from, ['annual', 'hourly'], true) || ! in_array($to, ['annual', 'hourly'], true)) {
            return null;
        }
        if ($from === $to) {
            return $amountMinor;
        }
        if ($hoursHundredths === null || $weeksHundredths === null || $hoursHundredths <= 0 || $weeksHundredths <= 0) {
            return null;
        }
        $hours = $hoursHundredths * $weeksHundredths;

        return $from === 'hourly' ? $this->divideHalfUp($amountMinor * $hours, 10000) : $this->divideHalfUp($amountMinor * 10000, $hours);
    }

    /** @return array{comparable:bool,reason:string,benchmark_minor:?int,difference_minor:?int,difference_percent:?float,formula:string} */
    public function compare(Employee $employee, ?PayRecord $actual, PayBenchmark $benchmark, string $asOf): array
    {
        $reason = match (true) {
            $actual === null => 'No effective actual pay is recorded for this date.',
            $actual->currency !== $benchmark->currency => 'Currencies differ; no exchange rate is assumed.',
            $employee->job_role_id !== $benchmark->job_role_id => 'Job roles differ; HR must establish duty comparability.',
            $benchmark->valid_from->toDateString() > $asOf || ($benchmark->valid_to !== null && $benchmark->valid_to->toDateString() < $asOf) => 'The benchmark is outside its recorded validity period.',
            $benchmark->correction()->exists() => 'This benchmark has been superseded by a correction.',
            default => '',
        };
        $converted = $actual === null ? null : $this->convert($benchmark->amount_minor, $benchmark->basis, $actual->basis, $benchmark->weekly_hours_hundredths, $benchmark->weeks_per_year_hundredths);
        if ($reason === '' && $converted === null) {
            $reason = 'Explicit weekly hours and weeks per year are required for this basis conversion.';
        }
        if ($reason === '' && $converted === 0) {
            $reason = 'The converted benchmark rounds to zero; a percentage comparison is unavailable.';
        }
        $comparable = $reason === '';
        $formula = $benchmark->basis === ($actual->basis ?? null) ? 'Same recorded basis; no working-time conversion.' : sprintf('%s %s %s %s (%s hours/week × %s weeks/year), rounded half-up to the minor unit.', number_format($benchmark->amount_minor / 100, 2), $benchmark->currency, $benchmark->basis, $benchmark->basis === 'hourly' ? '×' : '÷', $benchmark->weekly_hours_hundredths === null ? 'not recorded' : number_format($benchmark->weekly_hours_hundredths / 100, 2), $benchmark->weeks_per_year_hundredths === null ? 'not recorded' : number_format($benchmark->weeks_per_year_hundredths / 100, 2));

        return ['comparable' => $comparable, 'reason' => $reason, 'benchmark_minor' => $comparable ? $converted : null, 'difference_minor' => $comparable ? $actual->amount_minor - $converted : null, 'difference_percent' => $comparable ? round(($actual->amount_minor - $converted) / $converted * 100, 2, PHP_ROUND_HALF_UP) : null, 'formula' => $formula];
    }

    private function divideHalfUp(int $numerator, int $denominator): int
    {
        return intdiv($numerator, $denominator) + ($numerator % $denominator >= intdiv($denominator, 2) + $denominator % 2 ? 1 : 0);
    }
}
