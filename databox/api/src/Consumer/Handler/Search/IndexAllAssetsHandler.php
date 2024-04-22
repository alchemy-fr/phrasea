<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use Alchemy\ESBundle\Indexer\SearchIndexer;
use App\Consumer\Handler\AbstractBatchHandler;
use App\Entity\Core\Asset;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IndexAllAssetsHandler extends AbstractBatchHandler
{
    public function __invoke(IndexAllAssets $message): void
    {
        parent::doHandle();
    }

    protected function getIterator(): iterable
    {
        return $this->em
            ->createQueryBuilder()
            ->select('a.id')
            ->from(Asset::class, 'a')
            ->getQuery()
            ->toIterable();
    }

    protected function flushIndexStack(array $stack): void
    {
        $this->searchIndexer->scheduleObjectsIndex(Asset::class, $stack, SearchIndexer::ACTION_UPSERT);
    }
}
