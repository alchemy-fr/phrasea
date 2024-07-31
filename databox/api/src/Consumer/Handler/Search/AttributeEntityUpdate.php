<?php

namespace App\Consumer\Handler\Search;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class AttributeEntityUpdate
{
    public function __construct(
        private string $id,
        private array $changes,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getChanges(): array
    {
        return $this->changes;
    }
}
