<?php

declare(strict_types=1);

namespace App\Api\Extension;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Core\Workspace;
use App\Security\ScopeAwareTrait;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\WorkspaceVoter;
use Doctrine\ORM\QueryBuilder;

final class WorkspaceExtension implements QueryCollectionExtensionInterface
{
    use ScopeAwareTrait;

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $this->addWhere($queryBuilder, $resourceClass, $context);
    }

    private function addWhere(QueryBuilder $queryBuilder, string $resourceClass, array $context): void
    {
        if (Workspace::class !== $resourceClass) {
            return;
        }

        if ($this->hasScope(AbstractVoter::LIST, WorkspaceVoter::SCOPE_PREFIX)) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        $user = $this->security->getUser();
        if ($user instanceof JwtUser) {
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
