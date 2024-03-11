<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use Alchemy\ESBundle\Indexer\SearchIndexer;
use App\Consumer\Handler\AbstractBatchHandler;
use App\Entity\Core\Collection;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;

class IndexAllCollectionsHandler extends AbstractBatchHandler
{
    final public const EVENT = 'index_all_collections';

    public function __construct(private readonly SearchIndexer $searchIndexer)
    {
    }

    protected function getIterator(EventMessage $message): iterable
    {
        return $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('c.id')
            ->from(Collection::class, 'c')
            ->getQuery()
            ->toIterable();
    }

    protected function flushIndexStack(array $stack): void
    {
        $this->searchIndexer->scheduleObjectsIndex(Collection::class, $stack, SearchIndexer::ACTION_UPSERT);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
