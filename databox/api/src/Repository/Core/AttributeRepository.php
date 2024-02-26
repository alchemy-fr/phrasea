<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;


class AttributeRepository extends ServiceEntityRepository implements AttributeRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
    )
    {
        parent::__construct($registry, Attribute::class);
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

    public function getAssetAttributes(Asset $asset): array
    {
        return $this
            ->createQueryBuilder('a')
            ->select('a')
            ->andWhere('a.asset = :asset')
            ->setParameter('asset', $asset->getId())
            ->innerJoin('a.definition', 'd')
            ->addOrderBy('d.position', 'ASC')
            ->addOrderBy('d.name', 'ASC')
            ->addOrderBy('a.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getESQueryBuilder(): QueryBuilder
    {
        $types = array_map(
            fn (AttributeTypeInterface $type): string => $type::getName(),
            array_filter(
                $this->attributeTypeRegistry->getTypes(),
                fn (AttributeTypeInterface $type): bool => $type->supportsSuggest()
            )
        );

        return $this
            ->createQueryBuilder('t')
            ->addOrderBy('t.asset', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->innerJoin('t.definition', 'd')
            ->andWhere('d.fieldType IN (:types)')
            ->setParameter('types', $types)
        ;
    }
}
