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
