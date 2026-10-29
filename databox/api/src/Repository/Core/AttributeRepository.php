<?php

declare(strict_types=1);

namespace App\Repository\Core;

use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;

class AttributeRepository extends ServiceEntityRepository
{
    private const string CACHE_KEY_PREFIX = 'a.';

    private readonly CacheInterface $attributeCache;

    public function __construct(
        ManagerRegistry $registry,
        TemporaryCacheFactory $cacheFactory,
    ) {
        parent::__construct($registry, Attribute::class);
        $this->attributeCache = $cacheFactory->createCache();
    }

    /**
     * @return string[]
     */
    public function getDuplicates(Attribute $attribute): array
    {
        $definition = $attribute->getDefinition();

        $queryBuilder = $this
            ->createQueryBuilder('a')
            ->select('a')
            ->andWhere('a.definition = :definition')
            ->andWhere('a.asset = :asset')
            ->andWhere('a.id != :id')
            ->setParameter('definition', $definition->getId())
            ->setParameter('asset', $attribute->getAsset()->getId())
            ->setParameter('id', $attribute->getId());

        if ($definition->isTranslatable()) {
            $queryBuilder
                ->andWhere('a.locale = :locale')
                ->setParameter('locale', $attribute->getLocale())
            ;
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    private function getAssetAttributes(string $assetId): array
    {
        return $this
            ->createAssetAttributesQueryBuilder()
            ->andWhere('a.asset = :asset')
            ->setParameter('asset', $assetId)
            ->getQuery()
            ->getResult();
    }

    private function createAssetAttributesQueryBuilder(): QueryBuilder
    {
        return $this
            ->createQueryBuilder('a')
            ->select('a, d')
            ->andWhere('d.enabled = true')
            ->innerJoin('a.definition', 'd')
            ->addOrderBy('d.position', 'ASC')
            ->addOrderBy('d.name', 'ASC')
            ->addOrderBy('a.position', 'ASC')
            ->addOrderBy('a.value', 'ASC')
            ->addOrderBy('a.id', 'ASC')
        ;
    }

    /**
     * Load the attributes of many assets in a single query and fill the
     * per-request cache used by getCachedAssetAttributes().
     *
     * @param string[] $assetIds
     */
    public function prefetchAssetAttributes(array $assetIds): void
    {
        $assetIds = array_values(array_unique($assetIds));
        if (empty($assetIds)) {
            return;
        }

        $byAsset = array_fill_keys($assetIds, []);
        /** @var Attribute $attribute */
        foreach ($this->createAssetAttributesQueryBuilder()
            ->andWhere('a.asset IN (:assets)')
            ->setParameter('assets', $assetIds)
            ->getQuery()
            ->getResult() as $attribute) {
            if ($attribute->isValidValue()) {
                $byAsset[$attribute->getAsset()->getId()][] = $attribute;
            }
        }

        foreach ($byAsset as $assetId => $attributes) {
            $this->attributeCache->get($assetId, fn (): array => $attributes);
        }
    }

    public function resetAssetCache(Asset $asset): void
    {
        $this->attributeCache->delete(self::CACHE_KEY_PREFIX.$asset->getId());
    }

    public function getCachedAssetAttributes(string $assetId): array
    {
        return $this->attributeCache->get(self::CACHE_KEY_PREFIX.$assetId, fn (): array => array_filter($this->getAssetAttributes($assetId), fn (Attribute $attribute): bool => $attribute->isValidValue()));
    }

    /**
     * The attribute values carrying an embedding in the definition (the faces of the workspace),
     * oldest first, as plain rows: these scans go through every face of the workspace.
     *
     * @return iterable<array{id: string, asset: string, value: string, origin: int, confidence: float, vector: float[]}>
     */
    public function iterateEmbeddings(string $definitionId, ?string $excludeAssetId = null): iterable
    {
        $qb = $this->createEmbeddingsQueryBuilder($definitionId, $excludeAssetId)
            ->select('a.id', 'IDENTITY(a.asset) AS assetId', 'a.value', 'a.origin', 'a.confidence', 'a.embedding');

        foreach ($qb->getQuery()->toIterable([], AbstractQuery::HYDRATE_ARRAY) as $row) {
            yield [
                'id' => (string) $row['id'],
                'asset' => (string) $row['assetId'],
                'value' => (string) $row['value'],
                'origin' => (int) $row['origin'],
                'confidence' => (float) $row['confidence'],
                'vector' => $row['embedding']['v'] ?? [],
            ];
        }
    }

    /**
     * The attribute values carrying an embedding in the definition that were not set by a user,
     * i.e. the candidates for a recognition change (hydrated, detach the ones left untouched).
     *
     * @return iterable<Attribute>
     */
    public function iterateMachineEmbeddings(string $definitionId, ?string $excludeAssetId = null): iterable
    {
        return $this->createEmbeddingsQueryBuilder($definitionId, $excludeAssetId)
            ->select('a')
            ->andWhere('a.origin != :human')
            ->setParameter('human', Attribute::ORIGIN_HUMAN)
            ->getQuery()
            ->toIterable();
    }

    /**
     * The faces (values with an embedding) of an asset in the definition, in their order.
     *
     * @return Attribute[]
     */
    public function findEmbeddingsOfAsset(string $definitionId, string $assetId): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.definition = :def')
            ->andWhere('a.asset = :asset')
            ->andWhere('a.embedding IS NOT NULL')
            ->setParameter('def', $definitionId)
            ->setParameter('asset', $assetId)
            ->orderBy('a.position', 'ASC')
            ->addOrderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * The most confident detection of the value in the definition (the natural reference of a person).
     */
    public function findMostConfidentEmbedding(string $definitionId, string $value): ?Attribute
    {
        return $this->createEmbeddingsQueryBuilder($definitionId)
            ->select('a')
            ->andWhere('a.value = :value')
            ->setParameter('value', $value)
            ->orderBy('a.confidence', 'DESC')
            ->addOrderBy('a.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * How many attribute values of the definitions reference each value, and how many of them
     * were set by a user.
     *
     * @param string[] $definitionIds
     * @param string[] $values
     *
     * @return array<string, array{attributes: int, humanAttributes: int}>
     */
    public function countUsage(array $definitionIds, array $values): array
    {
        if (empty($definitionIds) || empty($values)) {
            return [];
        }

        $rows = $this->createQueryBuilder('a')
            ->select('a.value AS value', 'COUNT(a.id) AS attributes', 'SUM(CASE WHEN a.origin = :human THEN 1 ELSE 0 END) AS humanAttributes')
            ->innerJoin('a.asset', 'ast')
            ->andWhere('a.definition IN (:defs)')
            ->andWhere('a.value IN (:values)')
            ->andWhere('ast.deletedAt IS NULL')
            ->setParameter('defs', $definitionIds)
            ->setParameter('values', $values)
            ->setParameter('human', Attribute::ORIGIN_HUMAN)
            ->groupBy('a.value')
            ->getQuery()
            ->getArrayResult();

        $usage = [];
        foreach ($rows as $row) {
            $usage[(string) $row['value']] = [
                'attributes' => (int) $row['attributes'],
                'humanAttributes' => (int) $row['humanAttributes'],
            ];
        }

        return $usage;
    }

    /**
     * The values of the definitions that are not referenced by any attribute value any more.
     *
     * @param string[] $definitionIds
     * @param string[] $values
     *
     * @return string[]
     */
    public function findUnusedValues(array $definitionIds, array $values): array
    {
        $values = array_values(array_unique($values));
        if (empty($values)) {
            return [];
        }
        if (empty($definitionIds)) {
            return $values;
        }

        $used = array_column($this->createQueryBuilder('a')
            ->select('DISTINCT a.value AS value')
            ->andWhere('a.definition IN (:defs)')
            ->andWhere('a.value IN (:values)')
            ->setParameter('defs', $definitionIds)
            ->setParameter('values', $values)
            ->getQuery()
            ->getArrayResult(), 'value');

        return array_values(array_diff($values, $used));
    }

    private function createEmbeddingsQueryBuilder(string $definitionId, ?string $excludeAssetId = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')
            ->innerJoin('a.asset', 'ast')
            ->andWhere('a.definition = :def')
            ->andWhere('a.embedding IS NOT NULL')
            ->andWhere('ast.deletedAt IS NULL')
            ->setParameter('def', $definitionId)
            ->orderBy('a.createdAt', 'ASC')
            ->addOrderBy('a.position', 'ASC')
            ->addOrderBy('a.id', 'ASC');

        if (null !== $excludeAssetId) {
            $qb
                ->andWhere('a.asset != :excludedAsset')
                ->setParameter('excludedAsset', $excludeAssetId);
        }

        return $qb;
    }

    public function deleteByAttributeEntity(string $entityId, string $workspaceId, string $entityListId): void
    {
        $expr = $this->getEntityManager()->getExpressionBuilder();
        $this
            ->createQueryBuilder('t')
            ->delete()
            ->andWhere($expr->in(
                't.id',
                $this
                    ->createQueryBuilder('a')
                    ->select('a.id')
                    ->innerJoin('a.definition', 'd')
                    ->andWhere('d.workspace = :w')
                    ->andWhere('d.type = :t')
                    ->andWhere('d.entityList = :etype')
                    ->andWhere('a.value = :id')
                    ->getDQL()
            ))
            ->setParameter('w', $workspaceId)
            ->setParameter('t', EntityAttributeType::getName())
            ->setParameter('etype', $entityListId)
            ->setParameter('id', $entityId)
            ->getQuery()
            ->execute();
    }

    /**
     * IDs of the assets holding the entity in one of the definitions bound to its list.
     *
     * @return iterable<string>
     */
    public function iterateAssetIdsByAttributeEntity(string $workspaceId, string $entityListId, string $entityId): iterable
    {
        $qb = $this
            ->createQueryBuilder('a')
            ->select('DISTINCT IDENTITY(a.asset) AS assetId')
            ->innerJoin('a.definition', 'd')
            ->andWhere('d.workspace = :w')
            ->andWhere('d.type = :t')
            ->andWhere('d.entityList = :list')
            ->andWhere('a.value = :id')
            ->setParameter('w', $workspaceId)
            ->setParameter('t', EntityAttributeType::getName())
            ->setParameter('list', $entityListId)
            ->setParameter('id', $entityId);

        foreach ($qb->getQuery()->toIterable([], AbstractQuery::HYDRATE_ARRAY) as $row) {
            yield (string) $row['assetId'];
        }
    }

    /**
     * Assets holding a value of the list (one of the given entities when given).
     *
     * @param string[]|null $entityIds
     *
     * @return iterable<string>
     */
    public function iterateAssetIdsByAttributeEntities(string $workspaceId, string $entityListId, ?array $entityIds): iterable
    {
        if (null !== $entityIds && empty($entityIds)) {
            return;
        }

        $qb = $this
            ->createQueryBuilder('a')
            ->select('DISTINCT IDENTITY(a.asset) AS assetId')
            ->innerJoin('a.definition', 'd')
            ->andWhere('d.workspace = :w')
            ->andWhere('d.type = :t')
            ->andWhere('d.entityList = :list')
            ->setParameter('w', $workspaceId)
            ->setParameter('t', EntityAttributeType::getName())
            ->setParameter('list', $entityListId);
        if (null !== $entityIds) {
            $qb->andWhere('a.value IN (:ids)')->setParameter('ids', $entityIds);
        }

        foreach ($qb->getQuery()->toIterable([], AbstractQuery::HYDRATE_ARRAY) as $row) {
            yield (string) $row['assetId'];
        }
    }

    public function deleteByAttributeEntityList(string $entityListId, string $workspaceId): void
    {
        $expr = $this->getEntityManager()->getExpressionBuilder();
        $this
            ->createQueryBuilder('t')
            ->delete()
            ->andWhere($expr->in(
                't.id',
                $this
                    ->createQueryBuilder('a')
                    ->select('a.id')
                    ->innerJoin('a.definition', 'd')
                    ->andWhere('d.workspace = :w')
                    ->andWhere('d.type = :t')
                    ->andWhere('d.entityList = :listId')
                    ->getDQL()
            ))
            ->setParameter('w', $workspaceId)
            ->setParameter('t', EntityAttributeType::getName())
            ->setParameter('listId', $entityListId)
            ->getQuery()
            ->execute();
    }

    public function replaceAttributeEntity(string $workspaceId, string $entityListId, $newId, array $previousIds): void
    {
        $expr = $this->getEntityManager()->getExpressionBuilder();
        $this
            ->createQueryBuilder('t')
            ->update()
            ->set('t.value', ':newValue')
            ->andWhere($expr->in(
                't.id',
                $this
                    ->createQueryBuilder('a')
                    ->select('a.id')
                    ->innerJoin('a.definition', 'd')
                    ->andWhere('d.workspace = :w')
                    ->andWhere('d.type = :t')
                    ->andWhere('d.entityList = :etype')
                    ->andWhere('a.value IN (:prev)')
                    ->getDQL()
            ))
            ->setParameter('w', $workspaceId)
            ->setParameter('t', EntityAttributeType::getName())
            ->setParameter('etype', $entityListId)
            ->setParameter('prev', $previousIds)
            ->setParameter('newValue', $newId)
            ->getQuery()
            ->execute();
    }
}
