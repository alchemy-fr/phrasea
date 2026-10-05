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
use App\Entity\Page\Page;
use Doctrine\ORM\QueryBuilder;

/**
 * Restricts the page list to the pages readable by the current user (see PageVoter::READ).
 */
final class PageExtension implements QueryCollectionExtensionInterface
{
    use SecurityAwareTrait;

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (Page::class !== $resourceClass || $this->isAdmin()) {
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
                Page::OBJECT_TYPE,
                $rootAlias,
                PermissionInterface::VIEW,
                false
            );
            $queryBuilder->andWhere(sprintf('%1$s.ownerId = :uid OR (%1$s.enabled = true AND (%1$s.public = true OR ace.id IS NOT NULL))', $rootAlias));
        } else {
            $queryBuilder->andWhere(sprintf('%1$s.public = true AND %1$s.enabled = true', $rootAlias));
        }
    }
}
