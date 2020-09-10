<?php

declare(strict_types=1);

namespace App\Doctrine;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Entity\Publication;
use Doctrine\ORM\QueryBuilder;

class PublicationExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null)
    {
        if (Publication::class === $resourceClass) {
            $rootAlias = $queryBuilder->getRootAliases()[0];
            $queryBuilder->leftJoin($rootAlias.'.profile', 'p');
            $queryBuilder->andWhere(sprintf('%s.config.publiclyListed = true OR p.config.publiclyListed = true', $rootAlias));
            $queryBuilder->andWhere(sprintf('%s.config.enabled = true AND (p.id IS NULL OR p.config.enabled = true)', $rootAlias));
            $this->addDateClause($queryBuilder, $rootAlias, 'beginsAt', -1);
            $this->addDateClause($queryBuilder, $rootAlias, 'expiresAt', 1);
            $queryBuilder->andWhere(sprintf('%s.parent IS NULL', $rootAlias));
            $queryBuilder->addOrderBy(sprintf('%s.title', $rootAlias), 'ASC');
            $queryBuilder->setParameter('now', date('Y-m-d H:i:s'));
        }
    }

    private function addDateClause(QueryBuilder $queryBuilder, string $rootAlias, string $column, int $way)
    {
        $sprintf = sprintf(
            '(%1$s.config.%2$s IS NULL OR %1$s.config.%2$s %3$s :now) AND (%1$s.config.%2$s IS NOT NULL OR (p.config.%2$s IS NULL OR p.config.%2$s %3$s :now))',
            $rootAlias,
            $column,
            $way > 0 ? '>=' : '<='
        );
        $queryBuilder->andWhere($sprintf);
    }
}
