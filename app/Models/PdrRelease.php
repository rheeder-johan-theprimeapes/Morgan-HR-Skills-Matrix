<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PdrReleaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['pdr_review_id', 'pdr_submission_id', 'pdr_feedback_id', 'summary', 'released_by', 'recorder_reason'])]
#[Hidden(['summary', 'recorder_reason'])]
class PdrRelease extends Model
{
    /** @use HasFactory<PdrReleaseFactory> */
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

    /** @return array{summary:'array'} */
    protected function casts(): array
    {
        return ['summary' => 'array'];
    }
}
