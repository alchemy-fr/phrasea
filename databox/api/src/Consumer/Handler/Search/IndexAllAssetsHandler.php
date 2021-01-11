<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Elasticsearch\ESSearchIndexer;
use App\Entity\Core\Asset;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;

class IndexAllAssetsHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'index_all_assets';

    private ESSearchIndexer $searchIndexer;

    public function __construct(ESSearchIndexer $searchIndexer)
    {
        $this->searchIndexer = $searchIndexer;
    }

    public function handle(EventMessage $message): void
    {
        $em = $this->getEntityManager();

        $iterator = $em->createQueryBuilder()
            ->select('a.id')
            ->from(Asset::class, 'a')
            ->getQuery()
            ->iterate();

        $batchSize = 200;
        $stack = [];

        $i = 0;
        foreach ($iterator as $asset) {
            $stack[] = $asset[0]['id'];
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
        $this->searchIndexer->scheduleObjectsIndex(Asset::class, $stack, ESSearchIndexer::ACTION_UPSERT);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
