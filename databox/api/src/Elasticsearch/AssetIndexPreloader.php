<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Entity\Core\Asset;
use App\Entity\Core\AssetEmbedding;
use App\Entity\Core\AssetRendition;
use App\Repository\Core\AttributeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Loads what the documents of a page of assets need (see AssetPostTransformListener) with
 * a few queries for the whole page, as plain values rather than entities: the attributes
 * (without their face embeddings), the embedding of each asset and its rendition definitions.
 *
 * An asset that was not preloaded is read straight from the database, without being kept:
 * the real-time indexing must never get a stale value.
 */
final class AssetIndexPreloader implements ResetInterface
{
    /**
     * @var array<string, list<float>|null>
     */
    private array $embeddings = [];

    /**
     * @var array<string, list<string>>
     */
    private array $renditionDefinitionIds = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AttributeRepository $attributeRepository,
    ) {
    }

    /**
     * @param iterable<object> $objects the objects of the page, only the assets are preloaded
     */
    public function preload(iterable $objects): void
    {
        $assetIds = [];
        foreach ($objects as $object) {
            if ($object instanceof Asset) {
                $assetIds[] = $object->getId();
            }
        }
        $assetIds = array_values(array_unique($assetIds));
        if ([] === $assetIds) {
            return;
        }

        $this->attributeRepository->prefetchAssetAttributes($assetIds, withEmbeddings: false);

        foreach ($assetIds as $assetId) {
            $this->embeddings[$assetId] = null;
            $this->renditionDefinitionIds[$assetId] = [];
        }

        foreach ($this->createEmbeddingQuery($assetIds)->getArrayResult() as $row) {
            $this->embeddings[(string) $row['assetId']] = $row['vector'];
        }

        foreach ($this->createRenditionQuery($assetIds)->getArrayResult() as $row) {
            $this->renditionDefinitionIds[(string) $row['assetId']][] = (string) $row['definitionId'];
        }
    }

    /**
     * @return list<float>|null
     */
    public function getEmbedding(string $assetId): ?array
    {
        if (array_key_exists($assetId, $this->embeddings)) {
            return $this->embeddings[$assetId];
        }

        $row = $this->createEmbeddingQuery([$assetId])->getArrayResult()[0] ?? null;

        return null !== $row ? $row['vector'] : null;
    }

    /**
     * @return list<string>
     */
    public function getRenditionDefinitionIds(string $assetId): array
    {
        if (isset($this->renditionDefinitionIds[$assetId])) {
            return $this->renditionDefinitionIds[$assetId];
        }

        return array_map(
            static fn (array $row): string => (string) $row['definitionId'],
            $this->createRenditionQuery([$assetId])->getArrayResult(),
        );
    }

    public function reset(): void
    {
        $this->embeddings = [];
        $this->renditionDefinitionIds = [];
    }

    /**
     * @param list<string> $assetIds
     */
    private function createEmbeddingQuery(array $assetIds): Query
    {
        return $this->em->createQueryBuilder()
            ->select('IDENTITY(e.asset) AS assetId', 'e.vector')
            ->from(AssetEmbedding::class, 'e')
            ->andWhere('e.asset IN (:ids)')
            ->setParameter('ids', $assetIds)
            ->getQuery();
    }

    /**
     * @param list<string> $assetIds
     */
    private function createRenditionQuery(array $assetIds): Query
    {
        return $this->em->createQueryBuilder()
            ->select('IDENTITY(r.asset) AS assetId', 'IDENTITY(r.definition) AS definitionId')
            ->from(AssetRendition::class, 'r')
            ->andWhere('r.asset IN (:ids)')
            ->andWhere('r.definition IS NOT NULL')
            ->setParameter('ids', $assetIds)
            ->getQuery();
    }
}
