<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PdrFeedbackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['pdr_review_id', 'pdr_submission_id', 'pdr_decision_id', 'revision', 'held_on', 'summary', 'development_message', 'recorded_by', 'recorder_reason'])]
#[Hidden(['summary', 'development_message', 'recorder_reason'])]
class PdrFeedback extends Model
{
    /** @use HasFactory<PdrFeedbackFactory> */
    use HasFactory;

    protected $table = 'pdr_feedback';

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Review records are immutable; append a permitted successor.');
        });
        static::deleting(function (): never {
            throw new LogicException('Review records cannot be deleted.');
        });
    }

    /** @return array{revision:'integer',held_on:'immutable_date'} */
    protected function casts(): array
    {
        return ['revision' => 'integer', 'held_on' => 'immutable_date'];
    }
}
