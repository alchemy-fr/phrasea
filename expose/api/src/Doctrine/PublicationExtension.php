<?php

declare(strict_types=1);

namespace App\Doctrine;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Publication;
use App\Security\ScopeInterface;
use Doctrine\ORM\QueryBuilder;

final class PublicationExtension implements QueryCollectionExtensionInterface
{
    use SecurityAwareTrait;

    /**
     * Deepest nesting of the publications listed to the readers who cannot edit them.
     */
    public const int MAX_ANCESTOR_DEPTH = 5;

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (Publication::class !== $resourceClass) {
            return;
        }

        $user = $this->security->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : null;

        $rootAlias = $queryBuilder->getRootAliases()[0];

        if (
            !$this->security->isGranted(JwtUser::ROLE_ADMIN)
            && !$this->hasScope(ScopeInterface::SCOPE_PUBLISH)
        ) {
            $queryBuilder->leftJoin($rootAlias.'.profile', 'p');

            $visibleConditions = implode(' AND ', [
                sprintf('(%1$s.config.publiclyListed = true OR (%1$s.config.publiclyListed IS NULL AND p.config.publiclyListed = true))', $rootAlias),
                $this->createVisibilityClause($rootAlias, 'p'),
                $this->createAncestorsVisibilityClause($queryBuilder, $rootAlias),
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

    /**
     * A publication is hidden with its ancestors: joins them up to MAX_ANCESTOR_DEPTH, a deeper
     * publication is not listed.
     */
    private function createAncestorsVisibilityClause(QueryBuilder $queryBuilder, string $rootAlias): string
    {
        $conditions = [];
        $childAlias = $rootAlias;
        for ($depth = 1; $depth <= self::MAX_ANCESTOR_DEPTH; ++$depth) {
            $ancestorAlias = 'ancestor'.$depth;
            $profileAlias = 'ancestor_profile'.$depth;
            $queryBuilder
                ->leftJoin($childAlias.'.parent', $ancestorAlias)
                ->leftJoin($ancestorAlias.'.profile', $profileAlias);

            $conditions[] = sprintf('(%s.id IS NULL OR (%s))', $ancestorAlias, $this->createVisibilityClause($ancestorAlias, $profileAlias));
            $childAlias = $ancestorAlias;
        }
        $conditions[] = sprintf('IDENTITY(%s.parent) IS NULL', $childAlias);

        return implode(' AND ', $conditions);
    }

    /**
     * Same rules as Publication::isEnabled(), getBeginsAt() and getExpiresAt(): a setting left
     * unset falls back to the one of the profile.
     */
    private function createVisibilityClause(string $alias, string $profileAlias): string
    {
        return implode(' AND ', [
            sprintf('(%1$s.config.enabled = true OR (%1$s.config.enabled IS NULL AND %2$s.config.enabled = true))', $alias, $profileAlias),
            $this->createDateClause($alias, $profileAlias, 'beginsAt', -1),
            $this->createDateClause($alias, $profileAlias, 'expiresAt', 1),
        ]);
    }

    private function createDateClause(string $alias, string $profileAlias, string $column, int $way): string
    {
        return sprintf(
            '((%1$s.config.%3$s IS NULL OR %1$s.config.%3$s %4$s :now) AND (%1$s.config.%3$s IS NOT NULL OR (%2$s.config.%3$s IS NULL OR %2$s.config.%3$s %4$s :now)))',
            $alias,
            $profileAlias,
            $column,
            $way > 0 ? '>=' : '<='
        );
    }
}
