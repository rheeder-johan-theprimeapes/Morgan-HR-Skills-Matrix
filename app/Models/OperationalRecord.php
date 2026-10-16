<?php

declare(strict_types=1);

namespace App\Models;

use App\OperationalMetric;
use Database\Factories\OperationalRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

#[Fillable(['employee_id', 'sequence', 'metric', 'unit', 'value_hundredths', 'is_interpretation', 'period_start', 'period_end', 'source', 'notes', 'recorded_by', 'supersedes_id', 'correction_reason'])]
#[Hidden(['value_hundredths', 'notes', 'source', 'correction_reason'])]
class OperationalRecord extends Model
{
    /** @use HasFactory<OperationalRecordFactory> */
    use HasFactory;

    /** @return HasOne<OperationalRecord, $this> */
    public function correction(): HasOne
    {
        return $this->hasOne(self::class, 'supersedes_id');
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Operational evidence is immutable; append a correction.');
        });
        static::deleting(function (): never {
            throw new LogicException('Operational history cannot be deleted.');
        });
    }

    /** @return array{sequence:'integer',metric:'App\OperationalMetric',value_hundredths:'integer',is_interpretation:'boolean',period_start:'immutable_date',period_end:'immutable_date'} */
    protected function casts(): array
    {
        return ['sequence' => 'integer', 'metric' => OperationalMetric::class, 'value_hundredths' => 'integer', 'is_interpretation' => 'boolean', 'period_start' => 'immutable_date', 'period_end' => 'immutable_date'];
    }
}
