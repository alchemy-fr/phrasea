<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use Alchemy\ESBundle\Indexer\SearchIndexer;
use App\Consumer\Handler\AbstractBatchHandler;
use App\Entity\Core\Collection;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IndexAllCollectionsHandler extends AbstractBatchHandler
{
    public function __invoke(IndexAllCollections $message): void
    {
        parent::doHandle();
    }

    protected function getIterator(): iterable
    {
        return $this
            ->em
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
}
