<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Doctrine\TagAwareQueryResultCache;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class AttributeRepository extends ServiceEntityRepository implements AttributeRepositoryInterface
{
    private TagAwareCacheInterface $doctrineCache;

    public function __construct(ManagerRegistry $registry, TagAwareCacheInterface $doctrineCache)
    {
        parent::__construct($registry, Attribute::class);
        $this->doctrineCache = $doctrineCache;
    }

    /**
     * @return string[]
     */
    public function getDuplicates(Attribute $attribute): array
    {
        return $this
            ->createQueryBuilder('a')
            ->select('a')
            ->andWhere('a.definition = :definition')
            ->andWhere('a.asset = :asset')
            ->andWhere('a.id != :id')
            ->setParameter('definition', $attribute->getDefinition()->getId())
            ->setParameter('asset', $attribute->getAsset()->getId())
            ->setParameter('id', $attribute->getId())
            ->getQuery()
            ->getResult();
    }

    public function getAssetAttributes(Asset $asset): array
    {
        $query = $this
            ->createQueryBuilder('a')
            ->select('a')
            ->andWhere('a.asset = :asset')
            ->setParameter('asset', $asset->getId())
            ->addOrderBy('a.definition', 'ASC')
            ->getQuery();

        $query
            ->setResultCache(new TagAwareQueryResultCache($this->doctrineCache, [self::LIST_TAG]))
            ->setResultCacheId('attr_'.$asset->getId());

        return $query
            ->getResult();
    }
}
