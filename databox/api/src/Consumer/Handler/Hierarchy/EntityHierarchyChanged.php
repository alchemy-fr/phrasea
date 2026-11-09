<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Hierarchy;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

/**
 * Entities moved in the hierarchy of a list (their subtrees included), or the list bindings changed (no entity IDs).
 */
#[MessengerMessage('p2')]
final readonly class EntityHierarchyChanged
{
    /**
     * @param string[]|null $entityIds null: the whole list
     */
    public function __construct(
        private string $workspaceId,
        private string $listId,
        private ?array $entityIds = null,
    ) {
    }

    public function getWorkspaceId(): string
    {
        return $this->workspaceId;
    }

    public function getListId(): string
    {
        return $this->listId;
    }

    /**
     * @return string[]|null
     */
    public function getEntityIds(): ?array
    {
        return $this->entityIds;
    }
}
