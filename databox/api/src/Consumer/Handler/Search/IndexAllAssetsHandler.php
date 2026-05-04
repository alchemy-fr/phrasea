<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use Alchemy\ESBundle\Indexer\Operation;
use App\Consumer\Handler\AbstractBatchHandler;
use App\Entity\Core\Asset;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IndexAllAssetsHandler extends AbstractBatchHandler
{
    public function __invoke(IndexAllAssets $message): void
    {
        parent::doHandle($message);
    }

    protected function getIterator(object $message): iterable
    {
        $queryBuilder = $this->em
            ->createQueryBuilder()
            ->select('a.id')
            ->from(Asset::class, 'a');

        if ($message->workspaceId) {
            $queryBuilder->andWhere('a.workspace = :wid')
                ->setParameter('wid', $message->workspaceId);
        }

        return $queryBuilder
            ->getQuery()
            ->toIterable();
    }

    protected function flushIndexStack(array $stack): void
    {
        $this->searchIndexer->scheduleObjectsIndex(Asset::class, $stack, Operation::Upsert);
    }
}
