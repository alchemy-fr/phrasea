<?php

declare(strict_types=1);

namespace App\Doctrine;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Voter\ScopeVoter;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Publication;
use App\Security\ScopeInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class PublicationExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        if (Publication::class !== $resourceClass) {
            return;
        }

        $user = $this->security->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : null;

        $rootAlias = $queryBuilder->getRootAliases()[0];

        if (
            !$this->security->isGranted(JwtUser::ROLE_ADMIN)
            && !$this->security->isGranted(ScopeVoter::PREFIX.ScopeInterface::SCOPE_PUBLISH)
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
                    $user->getGroups(),
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
