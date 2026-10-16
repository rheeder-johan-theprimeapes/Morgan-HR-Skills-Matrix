<?php

declare(strict_types=1);

namespace App\Models;

use App\DocumentVisibility;
use Database\Factories\HistoricalDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['employee_id', 'title', 'document_date', 'source', 'uploaded_by', 'original_name', 'storage_path', 'mime_type', 'byte_size', 'sha256', 'visibility', 'revision'])]
#[Hidden(['storage_path', 'sha256', 'uploaded_by'])]
class HistoricalDocument extends Model
{
    /** @use HasFactory<HistoricalDocumentFactory> */
    use HasFactory;

    /** @return BelongsTo<Employee,$this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return HasMany<DocumentVisibilityRevision,$this> */
    public function visibilityRevisions(): HasMany
    {
        return $this->hasMany(DocumentVisibilityRevision::class)->latest('revision');
    }

    protected static function booted(): void
    {
        static::updating(function (self $document): void {
            foreach (array_keys($document->getDirty()) as $field) {
                if (! in_array($field, ['visibility', 'revision', 'updated_at'], true)) {
                    throw new LogicException('Historical files and provenance are immutable. Upload a distinct source document.');
                }
            }
        });
        static::deleting(function (): never {
            throw new LogicException('Historical documents cannot be deleted without an approved retention process.');
        });
    }

    /** @return array{document_date:'immutable_date',byte_size:'integer',revision:'integer',visibility:'App\DocumentVisibility'} */
    protected function casts(): array
    {
        return ['document_date' => 'immutable_date', 'byte_size' => 'integer', 'revision' => 'integer', 'visibility' => DocumentVisibility::class];
    }
}
