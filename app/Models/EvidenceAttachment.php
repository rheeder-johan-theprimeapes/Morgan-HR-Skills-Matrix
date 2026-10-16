<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EvidenceAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['assessment_revision_id', 'uploaded_by', 'original_name', 'storage_path', 'mime_type', 'byte_size', 'sha256'])]
#[Hidden(['storage_path', 'sha256'])]
class EvidenceAttachment extends Model
{
    /** @use HasFactory<EvidenceAttachmentFactory> */
    use HasFactory;

    /** @return BelongsTo<AssessmentRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(AssessmentRevision::class, 'assessment_revision_id');
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Evidence is immutable; append evidence to a new revision.');
        });
        static::deleting(function (): never {
            throw new LogicException('Referenced evidence cannot be deleted.');
        });
    }

    /** @return array{byte_size: 'integer'} */
    protected function casts(): array
    {
        return ['byte_size' => 'integer'];
    }
}
