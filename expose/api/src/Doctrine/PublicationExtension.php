<?php

declare(strict_types=1);

namespace App\Doctrine;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Entity\Publication;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\Security;

class PublicationExtension implements QueryCollectionExtensionInterface
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        string $operationName = null
    ) {
        if (Publication::class !== $resourceClass) {
            return;
        }

        $user = $this->security->getUser();
        $ownerId = $user instanceof RemoteUser ? $user->getId() : null;

        $rootAlias = $queryBuilder->getRootAliases()[0];

        if (!$this->security->isGranted('ROLE_ADMIN')) {
            $queryBuilder->leftJoin($rootAlias.'.profile', 'p');

            $visibleConditions = implode(' AND ', [
                sprintf('(%s.config.publiclyListed = true OR p.config.publiclyListed = true)', $rootAlias),
                sprintf('(%s.config.enabled = true AND (p.id IS NULL OR p.config.enabled = true))', $rootAlias),
                $this->createDateClause($rootAlias, 'beginsAt', -1),
                $this->createDateClause($rootAlias, 'expiresAt', 1),
            ]);

            if (null !== $ownerId) {
                $queryBuilder->andWhere(sprintf('(%s.ownerId = :ownerId) OR (%s)', $rootAlias, $visibleConditions));
                $queryBuilder->setParameter('ownerId', $ownerId);
            } else {
                $queryBuilder->andWhere($visibleConditions);
            }

            $queryBuilder->setParameter('now', date('Y-m-d H:i:s'));
        }

        $queryBuilder->andWhere(sprintf('%s.parent IS NULL', $rootAlias));
        $queryBuilder->addOrderBy(sprintf('%s.title', $rootAlias), 'ASC');
    }

    private function createDateClause(string $rootAlias, string $column, int $way): string
    {
        return sprintf(
            '((%1$s.config.%2$s IS NULL OR %1$s.config.%2$s %3$s :now) AND (%1$s.config.%2$s IS NOT NULL OR (p.config.%2$s IS NULL OR p.config.%2$s %3$s :now)))',
            $rootAlias,
            $column,
            $way > 0 ? '>=' : '<='
        );
    }
}
