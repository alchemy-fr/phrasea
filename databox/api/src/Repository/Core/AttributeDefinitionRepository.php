<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\Asset;
use App\Entity\Core\AttributeDefinition;
use Doctrine\ORM\EntityRepository;

class AttributeDefinitionRepository extends EntityRepository
{
    /**
     * @return AttributeDefinition[]
     */
    public function getSearchableAttributes(?array $workspaceIds = null): array
    {
        $queryBuilder = $this
            ->createQueryBuilder('t')
            ->andWhere('t.searchable = true')
            ->andWhere('t.public = true')
        ;

        if (null !== $workspaceIds) {
            $queryBuilder
                ->andWhere('t.workspace IN (:w)')
                ->setParameter('w', $workspaceIds)
            ;
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}
