<?php

namespace App\Consumer\Handler\Collection;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class CollectionsRestore
{
    public function __construct(
        private array $ids,
    ) {
    }

    public function getIds(): array
    {
        return $this->ids;
    }
}
