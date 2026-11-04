<?php

declare(strict_types=1);

namespace App\Api\Extension;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\WorkspaceVoter;
use Doctrine\ORM\QueryBuilder;

final class WorkspaceExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    use SecurityAwareTrait;

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $this->addWhere($queryBuilder, $resourceClass);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (Workspace::class !== $resourceClass) {
            return;
        }

        // Access is checked by the voter, only hide the soft-deleted workspaces
        $this->excludeSoftDeleted($queryBuilder);
    }

    /**
     * A soft-deleted workspace is waiting for its hard delete (DeleteWorkspace
     * message): it must not be reachable anymore, even by the admins.
     */
    private function excludeSoftDeleted(QueryBuilder $queryBuilder): void
    {
        $queryBuilder->andWhere(sprintf('%s.deletedAt IS NULL', $queryBuilder->getRootAliases()[0]));
    }

    private function addWhere(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        if (Workspace::class !== $resourceClass) {
            return;
        }

        $this->excludeSoftDeleted($queryBuilder);

        if (
            $this->isAdmin()
            || $this->hasScope(AbstractVoter::LIST, WorkspaceVoter::SCOPE_PREFIX)) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        $user = $this->security->getUser();
        if ($user instanceof JwtUser) {
            $queryBuilder->addGroupBy($rootAlias.'.id');
            AccessControlEntryRepository::joinAcl(
                $queryBuilder,
                $user->getId(),
                $user->getGroups(),
                Workspace::OBJECT_TYPE,
                $rootAlias,
                PermissionInterface::VIEW,
                false
            );
            $queryBuilder->andWhere(sprintf('ace.id IS NOT NULL OR %1$s.ownerId = :uid OR %1$s.public = true', $rootAlias));
        } else {
            $queryBuilder->andWhere(sprintf('%1$s.public = true', $rootAlias));
        }
    }
}
