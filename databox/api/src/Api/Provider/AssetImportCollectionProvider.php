<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use App\Api\Traits\QueryPaginationTrait;
use App\Entity\Core\AssetImport;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\AssetContainerVoterInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The imports of a workspace, newest first: all of them to whoever can create assets in
 * the workspace, the user's own imports otherwise (see AssetImportVoter).
 */
final class AssetImportCollectionProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;
    use QueryPaginationTrait;

    protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $user = $this->getStrictUser();
        $workspaceId = self::getParameterId($operation, 'workspace')
            ?? throw new BadRequestHttpException('You must provide "workspace" to filter out results');
        $workspace = DoctrineUtil::findStrict($this->em, Workspace::class, $workspaceId);
        $this->denyAccessUnlessGranted(AbstractVoter::READ, $workspace, 'Cannot read Workspace');

        $queryBuilder = $this->em->getRepository(AssetImport::class)
            ->createQueryBuilder('t')
            ->andWhere('t.workspace = :ws')
            ->setParameter('ws', $workspace->getId())
            ->addOrderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'ASC');

        if (!$this->isGranted(AssetContainerVoterInterface::ASSET_CREATE, $workspace)) {
            $queryBuilder
                ->andWhere('t.ownerId = :uid')
                ->setParameter('uid', $user->getId());
        }

        return self::paginateQuery($operation, $queryBuilder);
    }
}
