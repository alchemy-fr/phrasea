<?php

namespace App\Consumer\Handler\Search;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class IndexCollectionAssets
{
    public function __construct(
        private string $collectionId,
    ) {
    }

    public function getCollectionId(): string
    {
        return $this->collectionId;
    }
}
