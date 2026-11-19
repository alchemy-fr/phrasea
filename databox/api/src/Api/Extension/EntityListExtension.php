<?php

declare(strict_types=1);

namespace App\Api\Extension;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Core\EntityList;
use App\Repository\Core\WorkspaceRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\QueryBuilder;

/**
 * Restricts the entity lists to the workspaces the user may read (EntityListVoter READ).
 */
final class EntityListExtension implements QueryCollectionExtensionInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly WorkspaceRepository $workspaceRepository,
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (EntityList::class !== $resourceClass) {
            return;
        }

        if ($this->isGranted(AbstractVoter::LIST, new EntityList())) {
            return;
        }

        $user = $this->getUser();
        $workspaceIds = $this->workspaceRepository->getReadableWorkspaceIds($user?->getId(), $user?->getGroups() ?? [], false);

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $param = $queryNameGenerator->generateParameterName('allowedWorkspaces');
        $queryBuilder
            ->andWhere(sprintf('%s.workspace IN (:%s)', $rootAlias, $param))
            ->setParameter($param, $workspaceIds);
    }
}
