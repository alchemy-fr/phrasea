<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use Alchemy\ESBundle\Indexer\Operation;
use Alchemy\ESBundle\Indexer\SearchIndexer;
use Doctrine\ORM\EntityManagerInterface;

abstract readonly class AbstractIndexIteratorHandler
{
    public function __construct(
        private SearchIndexer $searchIndexer,
        protected EntityManagerInterface $em,
    ) {
    }

    protected function indexObjects(string $class, iterable $iterator): void
    {
        $chunkSize = 1000;
        $i = 0;
        $ids = [];
        foreach ($iterator as $row) {
            $ids[] = $row['id'];

            if (++$i > $chunkSize) {
                $this->searchIndexer->flush();
                $i = 0;
                $this->searchIndexer->scheduleObjectsIndex($class, $ids, Operation::Upsert);
                $ids = [];
            }
        }

        if (!empty($ids)) {
            $this->searchIndexer->scheduleObjectsIndex($class, $ids, Operation::Upsert);
        }
    }
}
