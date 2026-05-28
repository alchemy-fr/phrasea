<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Service\Collection\CollectionAccessService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ComputeWorkspaceCollectionsHandler
{
    public function __construct(
        private CollectionAccessService $collectionAccessService,
    ) {
    }

    public function __invoke(ComputeWorkspaceCollections $message): void
    {
        $this->collectionAccessService->recomputeWorkspace($message->getId());
    }
}
