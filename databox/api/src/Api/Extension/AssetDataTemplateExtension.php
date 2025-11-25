<?php

declare(strict_types=1);

namespace App\Api\Extension;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Template\AssetDataTemplate;
use App\Security\ScopeTrait;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\AssetDataTemplateVoter;
use Doctrine\ORM\QueryBuilder;

class AssetDataTemplateExtension implements QueryCollectionExtensionInterface
{
    use ScopeTrait;

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
        if (AssetDataTemplate::class !== $resourceClass) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        if (!$this->hasScope(AbstractVoter::LIST, AssetDataTemplateVoter::SCOPE_PREFIX)) {
            $user = $this->security->getUser();
            if ($user instanceof JwtUser) {
                AccessControlEntryRepository::joinAcl(
                    $queryBuilder,
                    $user->getId(),
                    $user->getGroups(),
                    AssetDataTemplate::OBJECT_TYPE,
                    $rootAlias,
                    PermissionInterface::VIEW,
                    false
                );
                $queryBuilder->andWhere(sprintf('ace.id IS NOT NULL OR %1$s.ownerId = :uid OR %1$s.public = true', $rootAlias));
            } else {
                $queryBuilder->andWhere(sprintf('%1$s.public = true', $rootAlias));
            }
        }

        $filters = $context['filters'] ?? [];
        if (isset($filters['collection'])) {
            $queryBuilder
                ->andWhere(sprintf('%1$s.collection = :colId OR %1$s.collection IS NULL', $rootAlias))
                ->setParameter('colId', $filters['collection'])
            ;
        } else {
            $queryBuilder->andWhere(sprintf('%1$s.collection IS NULL', $rootAlias));
        }
    }
}
