<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\ESBundle\Indexer\Operation;
use Alchemy\ESBundle\Indexer\SearchIndexer;
use App\Entity\Core\Collection;
use App\Service\Collection\CollectionAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class IndexCollectionBranchHandler
{
    public function __construct(
        private SearchIndexer $searchIndexer,
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private CollectionAccessService $collectionAccessService,
    ) {
    }

    public function __invoke(IndexCollectionBranch $message): void
    {
        $collection = DoctrineUtil::findStrict($this->em, Collection::class, $message->getCollectionId());

        $this->collectionAccessService->computeCollection($collection);

        $this->handleChildren($collection, $message->isIndexAssets());
    }

    private function handleChildren(Collection $collection, bool $indexAssets): void
    {
        $this->searchIndexer->scheduleObjectsIndex(Collection::class, [$collection->getId()], Operation::Upsert);
        if ($indexAssets) {
            $this->bus->dispatch(new IndexCollectionAssets($collection->getId()));
        }

        foreach ($collection->getChildren() as $child) {
            $this->handleChildren($child, $indexAssets);
        }
    }
}
