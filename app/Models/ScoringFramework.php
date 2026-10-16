<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\FrameworkDefinition;
use Database\Factories\ScoringFrameworkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * @phpstan-import-type FrameworkConfig from FrameworkDefinition
 *
 * @property FrameworkConfig $definition
 */
#[Fillable(['predecessor_id', 'name', 'illustrative', 'approval_reference', 'revision', 'definition', 'created_by', 'published_by', 'published_at'])]
class ScoringFramework extends Model
{
    /** @use HasFactory<ScoringFrameworkFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (self $framework): void {
            if ($framework->getRawOriginal('published_at') !== null || self::query()->whereKey($framework->id)->whereNotNull('published_at')->exists()) {
                throw new LogicException('Published frameworks are immutable; create a successor.');
            }
        });
        static::deleting(function (): never {
            throw new LogicException('Framework versions cannot be deleted.');
        });
    }

    /** @return array{illustrative: 'boolean', revision: 'integer', definition: 'array', published_at: 'immutable_datetime'} */
    protected function casts(): array
    {
        return ['illustrative' => 'boolean', 'revision' => 'integer', 'definition' => 'array', 'published_at' => 'immutable_datetime'];
    }
}
