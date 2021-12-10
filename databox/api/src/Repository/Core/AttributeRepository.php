<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\Attribute;
use Doctrine\ORM\EntityRepository;

class AttributeRepository extends EntityRepository
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
            ->andWhere('a.id != :id')
            ->setParameter('definition', $attribute->getDefinition()->getId())
            ->setParameter('asset', $attribute->getAsset()->getId())
            ->setParameter('id', $attribute->getId())
            ->getQuery()
            ->getResult();
    }
}
