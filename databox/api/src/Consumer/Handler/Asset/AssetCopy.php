<?php

namespace App\Consumer\Handler\Asset;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class AssetCopy
{
    public function __construct(
        private string $userId,
        private array $groupsId,
        private string $id,
        private string $destination,
        private ?bool $link = null,
        private array $options = [],
    ) {

    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getGroupsId(): array
    {
        return $this->groupsId;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDestination(): string
    {
        return $this->destination;
    }

    public function getLink(): ?bool
    {
        return $this->link;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
