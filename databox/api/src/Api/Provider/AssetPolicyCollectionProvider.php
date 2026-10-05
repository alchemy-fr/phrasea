<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Entity\Core\AssetPolicy\AssetPolicy;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\AssetPolicyVoter;

class AssetPolicyCollectionProvider extends AbstractWorkspaceFilteredCollectionProvider
{
    public function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $workspace = $this->getWorkspace($context);
        // Reading a policy is restricted to workspace editors (see AssetPolicyVoter)
        if (!$this->hasScope(AbstractVoter::LIST, AssetPolicyVoter::SCOPE_PREFIX)) {
            $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $workspace, 'Cannot read asset policies of this workspace');
        }

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
