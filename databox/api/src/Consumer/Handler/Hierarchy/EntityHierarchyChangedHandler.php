<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Hierarchy;

use Alchemy\ESBundle\Indexer\Operation;
use Alchemy\ESBundle\Indexer\SearchIndexer;
use App\Entity\Core\AttributeEntity;
use App\Repository\Core\AttributeEntityRepository;
use App\Repository\Core\AttributeRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Targeted reindexing after a hierarchy change: the moved entities (for their parent, depth and ancestors)
 * and the assets holding one of them, whose "hierarchy" values are recomputed.
 */
#[AsMessageHandler]
final readonly class EntityHierarchyChangedHandler
{
    private const int CHUNK_SIZE = 200;

    public function __construct(
        private AttributeEntityRepository $attributeEntityRepository,
        private AttributeRepository $attributeRepository,
        private SearchIndexer $searchIndexer,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(EntityHierarchyChanged $message): void
    {
        $entityIds = $message->getEntityIds();
        if (null !== $entityIds) {
            $entityIds = $this->attributeEntityRepository->getDescendantIds($entityIds);
            foreach (array_chunk($entityIds, self::CHUNK_SIZE) as $chunk) {
                $this->searchIndexer->scheduleObjectsIndex(AttributeEntity::class, $chunk, Operation::Upsert);
            }
        }

        $assetIds = [];
        foreach ($this->attributeRepository->iterateAssetIdsByAttributeEntities(
            $message->getWorkspaceId(),
            $message->getListId(),
            $entityIds,
        ) as $assetId) {
            $assetIds[] = $assetId;
            if (\count($assetIds) >= self::CHUNK_SIZE) {
                $this->bus->dispatch(new FillAssetsHierarchy($message->getWorkspaceId(), $assetIds, $message->getListId()));
                $assetIds = [];
            }
        }

        if (!empty($assetIds)) {
            $this->bus->dispatch(new FillAssetsHierarchy($message->getWorkspaceId(), $assetIds, $message->getListId()));
        }
    }
}
