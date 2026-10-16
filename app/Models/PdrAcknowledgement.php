<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PdrAcknowledgementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['pdr_release_id', 'employee_id', 'actor_id', 'choice', 'comment'])]
#[Hidden(['comment'])]
class PdrAcknowledgement extends Model
{
    /** @use HasFactory<PdrAcknowledgementFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Review records are immutable; append a permitted successor.');
        });
        static::deleting(function (): never {
            throw new LogicException('Review records cannot be deleted.');
        });
    }
}
