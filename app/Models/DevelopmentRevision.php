<?php

declare(strict_types=1);

namespace App\Models;

use App\DevelopmentState;
use Database\Factories\DevelopmentRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['development_action_id', 'revision', 'change_type', 'title', 'description', 'starts_on', 'due_on', 'resource_label', 'resource_url', 'state', 'notes', 'completed_on', 'reason', 'actor_id', 'original_name', 'storage_path', 'mime_type', 'byte_size', 'sha256'])]
#[Hidden(['storage_path', 'sha256'])]
class DevelopmentRevision extends Model
{
    /** @use HasFactory<DevelopmentRevisionFactory> */
    use HasFactory;

    /** @return BelongsTo<DevelopmentAction,$this> */
    public function action(): BelongsTo
    {
        return $this->belongsTo(DevelopmentAction::class, 'development_action_id');
    }

    /** @return BelongsTo<User,$this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Development revisions are immutable.');
        });
        static::deleting(function (): never {
            throw new LogicException('Development revisions cannot be deleted.');
        });
    }

    /** @return array{revision:'integer',state:'App\DevelopmentState',starts_on:'immutable_date',due_on:'immutable_date',completed_on:'immutable_date',byte_size:'integer'} */
    protected function casts(): array
    {
        return ['revision' => 'integer', 'state' => DevelopmentState::class, 'starts_on' => 'immutable_date', 'due_on' => 'immutable_date', 'completed_on' => 'immutable_date', 'byte_size' => 'integer'];
    }
}
