<?php

namespace App\Consumer\Handler\Search;

final readonly class IndexCollectionBranch
{
    public function __construct(
        private string $collectionId
    ) {
    }

    public function getCollectionId(): string
    {
        return $this->collectionId;
    }
}
