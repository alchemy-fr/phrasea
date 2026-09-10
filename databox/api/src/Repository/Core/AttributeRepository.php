<?php

declare(strict_types=1);

namespace App\Repository\Core;

use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;

class AttributeRepository extends ServiceEntityRepository
{
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
        $queryBuilder = $this
            ->createQueryBuilder('a')
            ->select('a')
            ->andWhere('a.asset = :asset')
            ->andWhere('d.enabled = true')
            ->setParameter('asset', $assetId)
            ->innerJoin('a.definition', 'd')
            ->addOrderBy('d.position', 'ASC')
            ->addOrderBy('d.name', 'ASC')
            ->addOrderBy('a.position', 'ASC')
            ->addOrderBy('a.value', 'ASC')
            ->addOrderBy('a.id', 'ASC')
        ;

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function resetAssetCache(Asset $asset): void
    {
        $this->attributeCache->delete($asset->getId());
    }

    public function getCachedAssetAttributes(string $assetId): array
    {
        return $this->attributeCache->get($assetId, fn (): array => array_filter($this->getAssetAttributes($assetId), fn (Attribute $attribute): bool => $attribute->isValidValue()));
    }

    public function deleteByAttributeEntity(string $entityId, string $workspaceId, string $entityListId): void
    {
        $expr = $this->_em->getExpressionBuilder();
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

    public function deleteByAttributeEntityList(string $entityListId, string $workspaceId): void
    {
        $expr = $this->_em->getExpressionBuilder();
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
        $expr = $this->_em->getExpressionBuilder();
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
