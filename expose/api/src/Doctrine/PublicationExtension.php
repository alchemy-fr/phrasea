<?php

declare(strict_types=1);

namespace App\Doctrine;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Model\RemoteUser;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\ContextAwareQueryCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Entity\Publication;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\Security;

class PublicationExtension implements ContextAwareQueryCollectionExtensionInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        string $operationName = null,
        array $context = []
    ) {
        if (Publication::class !== $resourceClass) {
            return;
        }

        $user = $this->security->getUser();
        $userId = $user instanceof RemoteUser ? $user->getId() : null;

        $rootAlias = $queryBuilder->getRootAliases()[0];

        if (
            !$this->security->isGranted('ROLE_ADMIN')
            && !$this->security->isGranted('ROLE_PUBLISH')
        ) {
            $queryBuilder->leftJoin($rootAlias.'.profile', 'p');

            $visibleConditions = implode(' AND ', [
                sprintf('(%1$s.config.publiclyListed = true OR (%1$s.config.publiclyListed IS NULL AND p.config.publiclyListed = true))', $rootAlias),
                sprintf('(%1$s.config.enabled = true OR (%1$s.config.enabled IS NULL AND p.config.enabled = true))', $rootAlias),
                $this->createDateClause($rootAlias, 'beginsAt', -1),
                $this->createDateClause($rootAlias, 'expiresAt', 1),
            ]);

            if (null !== $userId) {
                AccessControlEntryRepository::joinAcl(
                    $queryBuilder,
                    $user->getId(),
                    $user->getGroupIds(),
                    'publication',
                    $rootAlias,
                    PermissionInterface::EDIT,
                    false
                );

                $queryBuilder->andWhere(sprintf('(%s) OR (%s)',
                    $visibleConditions,
                    implode(' OR ', [
                        sprintf('%s.ownerId = :uid', $rootAlias),
                        'ace.id IS NOT NULL',
                    ])
                ));
            } else {
                $queryBuilder->andWhere($visibleConditions);
            }

            $queryBuilder->setParameter('now', date('Y-m-d H:i:s'));
        }
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
