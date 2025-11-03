<?php

namespace App\Consumer\Handler\Asset;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class AssetsDelete
{
    public function __construct(
        private array $ids,
        private array $collections = [],
        private bool $hardDelete = false,
    ) {
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function getCollections(): array
    {
        return $this->collections;
    }

    public function isHardDelete(): bool
    {
        return $this->hardDelete;
    }
}
