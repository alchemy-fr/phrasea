<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Collection;

use App\Doctrine\Delete\CollectionDelete;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class DeleteCollectionHandler
{
    public function __construct(
        private CollectionDelete $collectionDelete,
    ) {
    }

    public function __invoke(DeleteCollection $message): void
    {
        $this->collectionDelete->deleteCollection($message->getId());
    }
}
