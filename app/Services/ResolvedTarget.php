<?php

declare(strict_types=1);

namespace App\Services;

class ResolvedTarget
{
    /** @param list<int> $targetIds */
    public function __construct(public readonly bool $applicable, public readonly ?int $ordinal, public readonly ?int $levelId, public readonly string $source, public readonly array $targetIds = [], public readonly ?string $exceptionReason = null) {}

    /** @return array{applicable: bool, ordinal: int|null, level_id: int|null, source: string, target_ids: list<int>, exception_reason: string|null} */
    public function toArray(): array
    {
        return ['applicable' => $this->applicable, 'ordinal' => $this->ordinal, 'level_id' => $this->levelId, 'source' => $this->source, 'target_ids' => $this->targetIds, 'exception_reason' => $this->exceptionReason];
    }
}
