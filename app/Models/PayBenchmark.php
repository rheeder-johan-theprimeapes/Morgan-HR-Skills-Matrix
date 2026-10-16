<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PayBenchmarkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

#[Fillable(['job_role_id', 'title', 'company_context', 'duties', 'limitations', 'source_date', 'valid_from', 'valid_to', 'amount_minor', 'currency', 'basis', 'weekly_hours_hundredths', 'weeks_per_year_hundredths', 'source', 'recorded_by', 'supersedes_id', 'correction_reason'])]
#[Hidden(['amount_minor', 'weekly_hours_hundredths', 'weeks_per_year_hundredths', 'source', 'correction_reason'])]
class PayBenchmark extends Model
{
    /** @use HasFactory<PayBenchmarkFactory> */
    use HasFactory;

    /** @return HasOne<PayBenchmark, $this> */
    public function correction(): HasOne
    {
        return $this->hasOne(self::class, 'supersedes_id');
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Pay evidence is immutable; append an explicit correction.');
        });
        static::deleting(function (): never {
            throw new LogicException('Pay history cannot be deleted.');
        });
    }

    /** @return array{amount_minor:'integer', weekly_hours_hundredths:'integer', weeks_per_year_hundredths:'integer', source_date:'immutable_date', valid_from:'immutable_date', valid_to:'immutable_date'} */
    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'weekly_hours_hundredths' => 'integer', 'weeks_per_year_hundredths' => 'integer', 'source_date' => 'immutable_date', 'valid_from' => 'immutable_date', 'valid_to' => 'immutable_date'];
    }
}
