<?php

declare(strict_types=1);

namespace App\Doctrine;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\PublicationProfile;
use App\Security\ScopeInterface;
use Doctrine\ORM\QueryBuilder;

class PublicationProfileExtension implements QueryCollectionExtensionInterface
{
    use SecurityAwareTrait;

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (PublicationProfile::class === $resourceClass) {
            $rootAlias = $queryBuilder->getRootAliases()[0];
            $queryBuilder->addOrderBy(sprintf('%s.name', $rootAlias), 'ASC');

            if (
                !$this->isAdmin()
                && !$this->hasScope(ScopeInterface::SCOPE_PUBLISH)
            ) {
                $user = $this->getStrictUser();

                AccessControlEntryRepository::joinAcl(
                    $queryBuilder,
                    $user->getId(),
                    $user->getGroups(),
                    'profile',
                    'o',
                    PermissionInterface::VIEW,
                    false
                );

                $queryBuilder->andWhere(implode(' OR ', [
                    'o.ownerId = :uid',
                    'ace.id IS NOT NULL',
                ]));
            }
        }
    }
}
