<?php

declare(strict_types=1);

namespace App\Doctrine;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Entity\PublicationProfile;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Security;

class PublicationProfileExtension implements QueryCollectionExtensionInterface
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null)
    {
        if (PublicationProfile::class === $resourceClass) {
            $rootAlias = $queryBuilder->getRootAliases()[0];
            $queryBuilder->addOrderBy(sprintf('%s.name', $rootAlias), 'ASC');
        }

        $user = $this->security->getUser();
        $userId = $user instanceof RemoteUser ? $user->getId() : null;
        if (!$userId) {
            throw new AccessDeniedHttpException('User must be authenticated');
        }

        AccessControlEntryRepository::joinAcl(
            $queryBuilder,
            $user->getId(),
            $user->getGroupIds(),
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
