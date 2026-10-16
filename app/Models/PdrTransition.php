<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PdrTransitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['pdr_review_id', 'pdr_submission_id', 'head_version', 'action', 'reason', 'actor_id'])]
#[Hidden(['reason'])]
class PdrTransition extends Model
{
    /** @use HasFactory<PdrTransitionFactory> */
    use HasFactory;

    /** @return BelongsTo<User,$this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<PdrSubmission,$this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(PdrSubmission::class, 'pdr_submission_id');
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Transition history is immutable.');
        });
        static::deleting(function (): never {
            throw new LogicException('Transition history cannot be deleted.');
        });
    }

    /** @return array{head_version:'integer'} */
    protected function casts(): array
    {
        return ['head_version' => 'integer'];
    }
}
