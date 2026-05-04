<?php

namespace App\Consumer\Handler\Search;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class AttributeEntityMerge
{
    public function __construct(
        private string $id,
        private array $merged,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getMerged(): array
    {
        return $this->merged;
    }
}
