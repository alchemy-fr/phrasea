<?php

declare(strict_types=1);

namespace App\Doctrine;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\UploadLink;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Users list the links they created, the admins every link.
 */
final readonly class UploadLinkExtension implements QueryCollectionExtensionInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (UploadLink::class !== $resourceClass || $this->security->isGranted(JwtUser::ROLE_ADMIN)) {
            return;
        }

        $user = $this->security->getUser();
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $param = $queryNameGenerator->generateParameterName('ownerId');
        $queryBuilder
            ->andWhere(sprintf('%s.ownerId = :%s', $rootAlias, $param))
            ->setParameter($param, $user instanceof JwtUser ? $user->getId() : '');
    }
}
