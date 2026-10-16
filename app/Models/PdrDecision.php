<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PdrDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['pdr_review_id', 'pdr_submission_id', 'actor_id', 'outcome', 'reason', 'approved_pay'])]
#[Hidden(['reason', 'approved_pay'])]
class PdrDecision extends Model
{
    /** @use HasFactory<PdrDecisionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Human decisions are immutable.');
        });
        static::deleting(function (): never {
            throw new LogicException('Human decisions cannot be deleted.');
        });
    }

    /** @return array{approved_pay:'array'} */
    protected function casts(): array
    {
        return ['approved_pay' => 'array'];
    }
}
