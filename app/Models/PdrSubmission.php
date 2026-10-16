<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PdrSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['pdr_review_id', 'pdr_draft_id', 'submission_number', 'previous_submission_id', 'schema_version', 'snapshot', 'submitted_by'])]
#[Hidden(['snapshot'])]
class PdrSubmission extends Model
{
    /** @use HasFactory<PdrSubmissionFactory> */
    use HasFactory;

    /** @return BelongsTo<PdrReview,$this> */
    public function review(): BelongsTo
    {
        return $this->belongsTo(PdrReview::class, 'pdr_review_id');
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Submitted revisions are immutable.');
        });
        static::deleting(function (): never {
            throw new LogicException('Submitted revisions cannot be deleted.');
        });
    }

    /** @return array{snapshot:'array',submission_number:'integer',schema_version:'integer'} */
    protected function casts(): array
    {
        return ['snapshot' => 'array', 'submission_number' => 'integer', 'schema_version' => 'integer'];
    }
}
