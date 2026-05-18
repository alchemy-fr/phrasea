<?php

namespace App\Consumer\Handler\Search;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class IndexCollectionBranch
{
    public function __construct(
        private string $collectionId,
        private bool $indexAssets = false,
    ) {
    }

    public function getCollectionId(): string
    {
        return $this->collectionId;
    }

    public function isIndexAssets(): bool
    {
        return $this->indexAssets;
    }
}
