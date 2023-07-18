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
use App\Entity\PublicationProfile;
use App\Security\ScopeInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

readonly class PublicationProfileExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private Security $security)
    {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        Operation $operation = null,
        array $context = []
    ): void {
        if (PublicationProfile::class === $resourceClass) {
            $rootAlias = $queryBuilder->getRootAliases()[0];
            $queryBuilder->addOrderBy(sprintf('%s.name', $rootAlias), 'ASC');

            if (
                !$this->security->isGranted(JwtUser::ROLE_ADMIN)
                && !$this->security->isGranted(ScopeVoter::PREFIX.ScopeInterface::SCOPE_PUBLISH)
            ) {
                $user = $this->security->getUser();
                $userId = $user instanceof JwtUser ? $user->getId() : null;
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
    }
}
