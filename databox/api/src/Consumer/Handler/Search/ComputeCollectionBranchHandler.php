<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Entity\Core\Collection;
use App\Repository\Core\CollectionRepository;
use App\Service\Collection\CollectionAccessService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ComputeCollectionBranchHandler
{
    public function __construct(
        private CollectionRepository $collectionRepository,
        private CollectionAccessService $collectionAccessService,
    ) {
    }

    public function __invoke(ComputeCollectionBranch $message): void
    {
        if (null === $message->getCollectionId()) {
            $this->collectionAccessService->recomputeAll();

            return;
        }

        $collection = $this->collectionRepository->find($message->getCollectionId());
        if (!$collection instanceof Collection) {
            return;
        }

        $this->collectionAccessService->computeCollection($collection);
    }
}
