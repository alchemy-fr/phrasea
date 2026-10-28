<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Hierarchy;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

/**
 * Recomputes the "hierarchy" values of assets (manual values are kept, conflicts reported).
 */
#[MessengerMessage('p1')]
final readonly class FillAssetsHierarchy
{
    /**
     * @param string[] $assetIds
     */
    public function __construct(
        private string $workspaceId,
        private array $assetIds,
        private ?string $listId = null,
    ) {
    }

    public function getWorkspaceId(): string
    {
        return $this->workspaceId;
    }

    /**
     * @return string[]
     */
    public function getAssetIds(): array
    {
        return $this->assetIds;
    }

    public function getListId(): ?string
    {
        return $this->listId;
    }
}
