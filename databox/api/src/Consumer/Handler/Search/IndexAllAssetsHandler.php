<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Consumer\Handler\AbstractBatchHandler;
use App\Elasticsearch\ESSearchIndexer;
use App\Entity\Core\Asset;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;

class IndexAllAssetsHandler extends AbstractBatchHandler
{
    const EVENT = 'index_all_assets';

    private ESSearchIndexer $searchIndexer;

    public function __construct(ESSearchIndexer $searchIndexer)
    {
        $this->searchIndexer = $searchIndexer;
    }

    protected function getIterator(EventMessage $message): iterable
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('a.id')
            ->from(Asset::class, 'a')
            ->getQuery()
            ->toIterable();
    }

    protected function flushIndexStack(array $stack): void
    {
        $this->searchIndexer->scheduleObjectsIndex(Asset::class, $stack, ESSearchIndexer::ACTION_UPSERT);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
