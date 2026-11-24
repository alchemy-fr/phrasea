<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Entity\Core\AssetPolicy\AssetPolicy;
use App\Security\Voter\AbstractVoter;

class AssetPolicyCollectionProvider extends AbstractWorkspaceFilteredCollectionProvider
{
    public function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $workspace = $this->getWorkspace($operation);
        $listedPolicy = new AssetPolicy();
        $listedPolicy->setWorkspace($workspace);
        $this->denyAccessUnlessGranted(AbstractVoter::LIST, $listedPolicy, 'Cannot list asset policies of this workspace');

        return $this->em->getRepository(AssetPolicy::class)
            ->createQueryBuilder('t')
            ->andWhere('t.workspace = :wid')
            ->setParameter('wid', $workspace->getId())
            ->addOrderBy('t.name', 'ASC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
