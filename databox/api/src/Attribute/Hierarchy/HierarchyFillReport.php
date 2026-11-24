<?php

declare(strict_types=1);

namespace App\Attribute\Hierarchy;

use App\Entity\Core\Attribute;

final class HierarchyFillReport
{
    /**
     * @var array<array{assetId: string, conflict: HierarchyConflict}>
     */
    private array $conflicts = [];

    /**
     * @var array<string, true>
     */
    private array $changedAssets = [];

    public function addConflict(string $assetId, HierarchyConflict $conflict): void
    {
        $this->conflicts[] = ['assetId' => $assetId, 'conflict' => $conflict];
    }

    public function addFilled(string $assetId, Attribute $attribute): void
    {
        $this->changedAssets[$assetId] = true;
    }

    public function addRemoved(string $assetId, Attribute $attribute): void
    {
        $this->changedAssets[$assetId] = true;
    }

    public function hasConflicts(): bool
    {
        return !empty($this->conflicts);
    }

    /**
     * @return array<array{assetId: string, conflict: HierarchyConflict}>
     */
    public function getConflicts(): array
    {
        return $this->conflicts;
    }

    /**
     * @return string[]
     */
    public function getChangedAssetIds(): array
    {
        return array_keys($this->changedAssets);
    }
}
