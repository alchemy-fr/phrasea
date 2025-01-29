<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\ESBundle\Indexer\Operation;
use Alchemy\ESBundle\Indexer\SearchIndexer;
use App\Entity\Core\Collection;
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
    ) {
    }

    public function __invoke(IndexCollectionBranch $message): void
    {
        $collection = DoctrineUtil::findStrict($this->em, Collection::class, $message->getCollectionId());

        $this->handleChildren($collection);
    }

    private function handleChildren(Collection $collection): void
    {
        $this->indexCollection($collection);
        foreach ($collection->getChildren() as $child) {
            $this->handleChildren($child);
        }
    }

    private function indexCollection(Collection $collection): void
    {
        $this->searchIndexer->scheduleObjectsIndex(Collection::class, [$collection->getId()], Operation::Upsert);
        $this->bus->dispatch(new IndexCollectionAssets($collection->getId()));
    }
}
