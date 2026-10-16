<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PdrDraftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property array<string,mixed> $employee_section
 * @property array<string,mixed> $manager_section
 * @property array<string,mixed>|null $pay_proposal
 */
#[Fillable(['previous_submission_id', 'pdr_review_id', 'draft_number', 'revision', 'employee_section', 'manager_section', 'pay_proposal', 'locked_at', 'last_saved_by'])]
#[Hidden(['employee_section', 'manager_section', 'pay_proposal'])]
class PdrDraft extends Model
{
    /** @use HasFactory<PdrDraftFactory> */
    use HasFactory;

    /** @return BelongsTo<PdrReview,$this> */
    public function review(): BelongsTo
    {
        return $this->belongsTo(PdrReview::class, 'pdr_review_id');
    }

    protected static function booted(): void
    {
        static::updating(function (self $draft): void {
            if ($draft->getRawOriginal('locked_at') !== null || self::query()->whereKey($draft->id)->whereNotNull('locked_at')->exists()) {
                throw new LogicException('Submitted drafts are immutable; use a successor.');
            }
        });
        static::deleting(function (): never {
            throw new LogicException('Draft history cannot be deleted.');
        });
    }

    /** @return array{draft_number:'integer',revision:'integer',employee_section:'array',manager_section:'array',pay_proposal:'array',locked_at:'immutable_datetime'} */
    protected function casts(): array
    {
        return ['draft_number' => 'integer', 'revision' => 'integer', 'employee_section' => 'array', 'manager_section' => 'array', 'pay_proposal' => 'array', 'locked_at' => 'immutable_datetime'];
    }
}
