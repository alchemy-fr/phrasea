<?php

namespace App\Consumer\Handler\Search;

use Alchemy\ESBundle\Indexer\Operation;
use Alchemy\ESBundle\Indexer\SearchIndexer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

abstract readonly class AbstractIndexIteratorHandler
{
    public function __construct(
        private SearchIndexer $searchIndexer,
        protected EntityManagerInterface $em,
        protected MessageBusInterface $bus,
    ) {
    }

    protected function indexObjects(string $class, iterable $iterator, ?\Closure $onFlush = null): void
    {
        $chunkSize = 1000;
        $i = 0;
        $ids = [];
        foreach ($iterator as $row) {
            if ($i++ > $chunkSize) {
                $this->searchIndexer->flush();
                $i = 0;
                $this->searchIndexer->scheduleObjectsIndex($class, $ids, Operation::Upsert);
                $onFlush && $onFlush($ids);
                $ids = [];
            }
            $ids[] = $row['id'];
        }

        if (!empty($ids)) {
            $this->searchIndexer->scheduleObjectsIndex($class, $ids, Operation::Upsert);
            $onFlush && $onFlush($ids);
        }
    }
}
