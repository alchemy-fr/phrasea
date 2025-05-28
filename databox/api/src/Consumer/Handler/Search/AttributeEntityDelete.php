<?php

namespace App\Consumer\Handler\Search;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class AttributeEntityDelete
{
    public function __construct(
        private string $id,
        private string $typeId,
        private string $wId,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTypeId(): string
    {
        return $this->typeId;
    }

    public function getWorkspaceId(): string
    {
        return $this->wId;
    }
}
