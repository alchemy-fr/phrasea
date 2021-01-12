<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Consumer\Handler\AbstractBatchHandler;
use App\Elasticsearch\ESSearchIndexer;
use App\Entity\Core\Collection;

class IndexAllCollectionsHandler extends AbstractBatchHandler
{
    const EVENT = 'index_all_collections';

    private ESSearchIndexer $searchIndexer;

    public function __construct(ESSearchIndexer $searchIndexer)
    {
        $this->searchIndexer = $searchIndexer;
    }

    protected function getIterator(): iterable
    {
        return $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('c.id')
            ->from(Collection::class, 'c')
            ->getQuery()
            ->iterate();
    }

    protected function flushIndexStack(array $stack): void
    {
        $this->searchIndexer->scheduleObjectsIndex(Collection::class, $stack, ESSearchIndexer::ACTION_UPSERT);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
