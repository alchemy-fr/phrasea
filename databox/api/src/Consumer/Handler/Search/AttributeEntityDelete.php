<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class AttributeEntityDelete
{
    public function __construct(
        private string $id,
        private string $listId,
        private string $workspaceId,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getListId(): string
    {
        return $this->listId;
    }

    public function getWorkspaceId(): string
    {
        return $this->workspaceId;
    }
}
