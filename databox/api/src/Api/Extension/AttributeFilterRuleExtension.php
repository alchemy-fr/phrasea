<?php

declare(strict_types=1);

namespace App\Api\Extension;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Core\AttributeFilterRule;
use App\Entity\Core\Workspace;
use Doctrine\ORM\QueryBuilder;

/**
 * Restricts the filter rules to the workspaces the user may edit (see AttributeFilterRuleVoter),
 * and applies the "workspaceId" filter.
 */
final class AttributeFilterRuleExtension implements QueryCollectionExtensionInterface
{
    use SecurityAwareTrait;

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (AttributeFilterRule::class !== $resourceClass) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        if (null !== $workspaceId = $context['filters']['workspaceId'] ?? null) {
            $param = $queryNameGenerator->generateParameterName('workspaceId');
            $queryBuilder
                ->andWhere(sprintf('%s.workspace = :%s', $rootAlias, $param))
                ->setParameter($param, $workspaceId);
        }

        if ($this->isAdmin()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof JwtUser) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        // Same rule as WorkspaceVoter::EDIT: owner, or EDIT or OWNER permission
        $workspaceAlias = $queryNameGenerator->generateJoinAlias('workspace');
        $queryBuilder
            ->innerJoin(sprintf('%s.workspace', $rootAlias), $workspaceAlias)
            ->addGroupBy(sprintf('%s.id', $rootAlias));

        foreach ([
            'afr_edit_' => PermissionInterface::EDIT,
            'afr_owner_' => PermissionInterface::OWNER,
        ] as $prefix => $permission) {
            AccessControlEntryRepository::joinAcl(
                $queryBuilder,
                $user->getId(),
                $user->getGroups(),
                Workspace::OBJECT_TYPE,
                $workspaceAlias,
                $permission,
                false,
                $prefix.'ace',
                $prefix,
            );
        }

        $queryBuilder->andWhere(sprintf(
            '%s.ownerId = :afr_edit_uid OR afr_edit_ace.id IS NOT NULL OR afr_owner_ace.id IS NOT NULL',
            $workspaceAlias,
        ));
    }
}
