<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Elasticsearch\ESSearchIndexer;
use App\Entity\Core\Collection;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;

class IndexAllCollectionsHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'index_all_collections';

    private ESSearchIndexer $searchIndexer;

    public function __construct(ESSearchIndexer $searchIndexer)
    {
        $this->searchIndexer = $searchIndexer;
    }

    public function handle(EventMessage $message): void
    {
        $em = $this->getEntityManager();

        $iterator = $em->createQueryBuilder()
            ->select('c.id')
            ->from(Collection::class, 'c')
            ->getQuery()
            ->iterate();

        $batchSize = 200;
        $stack = [];

        $i = 0;
        foreach ($iterator as $collection) {
            $stack[] = $collection[0]['id'];
            if ($i++ > $batchSize) {
                $this->flushIndexStack($stack);
                $stack = [];
                $i = 0;
            }
        }

        if (!empty($stack)) {
            $this->flushIndexStack($stack);
        }
    }

    private function flushIndexStack(array $stack): void
    {
        $this->searchIndexer->scheduleObjectsIndex(Collection::class, $stack, ESSearchIndexer::ACTION_UPSERT);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
