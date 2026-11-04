<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use Alchemy\ESBundle\Indexer\Operation;
use Alchemy\ESBundle\Indexer\SearchIndexer;
use App\Entity\Core\Asset;
use App\Repository\Core\AttributeRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Reindexes the assets holding an entity whose approval changed.
 *
 * A value that is not approved is left out of the asset document
 * (EntityAttributeType::normalizeElasticsearchValue): an update_by_query on the
 * existing documents cannot add it back, the assets are indexed again.
 */
#[AsMessageHandler]
final readonly class AttributeEntityStatusChangedHandler
{
    private const int CHUNK_SIZE = 500;

    public function __construct(
        private AttributeRepository $attributeRepository,
        private SearchIndexer $searchIndexer,
    ) {
    }

    public function __invoke(AttributeEntityStatusChanged $message): void
    {
        $ids = [];
        foreach ($this->attributeRepository->iterateAssetIdsByAttributeEntity(
            $message->getWorkspaceId(),
            $message->getListId(),
            $message->getId(),
        ) as $assetId) {
            $ids[] = $assetId;
            if (count($ids) >= self::CHUNK_SIZE) {
                $this->searchIndexer->scheduleObjectsIndex(Asset::class, $ids, Operation::Upsert);
                $ids = [];
            }
        }

        if (!empty($ids)) {
            $this->searchIndexer->scheduleObjectsIndex(Asset::class, $ids, Operation::Upsert);
        }
    }
}
