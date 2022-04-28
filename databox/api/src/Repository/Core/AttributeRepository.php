<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use Doctrine\ORM\EntityRepository;

class AttributeRepository extends EntityRepository implements AttributeRepositoryInterface
{
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
            ->andWhere('a.locale = :locale')
            ->andWhere('a.id != :id')
            ->setParameter('definition', $attribute->getDefinition()->getId())
            ->setParameter('asset', $attribute->getAsset()->getId())
            ->setParameter('locale', $attribute->getLocale())
            ->setParameter('id', $attribute->getId())
            ->getQuery()
            ->getResult();
    }

    public function getAssetAttributes(Asset $asset): array
    {
        return $this
            ->createQueryBuilder('a')
            ->select('a')
            ->andWhere('a.asset = :asset')
            ->setParameter('asset', $asset->getId())
            ->innerJoin('a.definition', 'd')
            ->addOrderBy('d.name', 'ASC')
            ->addOrderBy('a.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
