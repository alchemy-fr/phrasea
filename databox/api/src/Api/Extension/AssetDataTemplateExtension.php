<?php

declare(strict_types=1);

namespace App\Api\Extension;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\ContextAwareQueryCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Entity\Template\AssetDataTemplate;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\Security;

class AssetDataTemplateExtension implements ContextAwareQueryCollectionExtensionInterface
{
    private Security $security;
    private ObjectMapping $objectMapping;

    public function __construct(
        Security $security,
        ObjectMapping $objectMapping
    ) {
        $this->security = $security;
        $this->objectMapping = $objectMapping;
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        string $operationName = null,
        array $context = []
    ): void {
        $this->addWhere($queryBuilder, $resourceClass, $context);
    }

    private function addWhere(QueryBuilder $queryBuilder, string $resourceClass, array $context): void
    {
        if (AssetDataTemplate::class !== $resourceClass) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        $user = $this->security->getUser();
        if ($user instanceof RemoteUser) {
            AccessControlEntryRepository::joinAcl(
                $queryBuilder,
                $user->getId(),
                $user->getGroupIds(),
                $this->objectMapping->getObjectKey(AssetDataTemplate::class),
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
