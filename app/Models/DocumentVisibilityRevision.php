<?php

declare(strict_types=1);

namespace App\Models;

use App\DocumentVisibility;
use Database\Factories\DocumentVisibilityRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['historical_document_id', 'revision', 'visibility', 'content_review_confirmed', 'reason', 'actor_id'])]
class DocumentVisibilityRevision extends Model
{
    /** @use HasFactory<DocumentVisibilityRevisionFactory> */
    use HasFactory;

    /** @return BelongsTo<HistoricalDocument,$this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(HistoricalDocument::class, 'historical_document_id');
    }

    /** @return BelongsTo<User,$this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Visibility history is immutable.');
        });
        static::deleting(function (): never {
            throw new LogicException('Visibility history cannot be deleted.');
        });
    }

    /** @return array{revision:'integer',content_review_confirmed:'boolean',visibility:'App\DocumentVisibility'} */
    protected function casts(): array
    {
        return ['revision' => 'integer', 'content_review_confirmed' => 'boolean', 'visibility' => DocumentVisibility::class];
    }
}
