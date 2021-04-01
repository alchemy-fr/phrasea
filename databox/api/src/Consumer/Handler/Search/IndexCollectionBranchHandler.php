<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Consumer\Handler\AbstractBatchHandler;
use App\Elasticsearch\ESSearchIndexer;
use App\Entity\Core\Collection;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use phpDocumentor\Reflection\Types\Iterable_;

class IndexCollectionBranchHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'index_collection_branch';

    private ESSearchIndexer $searchIndexer;

    public function __construct(ESSearchIndexer $searchIndexer)
    {
        $this->searchIndexer = $searchIndexer;
    }

    public function handle(EventMessage $message): void
    {
        $id = $message->getPayload()['id'];

        $em = $this->getEntityManager();
        /** @var Collection $collection */
        $collection = $em->find(Collection::class, $id);

        $parent = $collection->getParent();
        while (null !== $parent) {
            $this->indexCollection($parent);
            $parent = $parent->getParent();
        }

        $this->handleChildren($collection);
    }

    private function handleChildren(Collection $collection): void
    {
        $this->indexCollection($collection);
        foreach ($collection->getChildren() as $child) {
            $this->handleChildren($child);
        }
    }

    private function indexCollection(Collection $collection): void
    {
        $this->searchIndexer->scheduleObjectsIndex(Collection::class, [$collection->getId()], ESSearchIndexer::ACTION_UPSERT);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
