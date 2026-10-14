<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use App\Api\Traits\ParameterValuesTrait;
use App\Entity\Core\RenditionPolicy;
use App\Security\Voter\AbstractVoter;

class RenditionPolicyCollectionProvider extends AbstractCollectionProvider
{
    use ParameterValuesTrait;

    use SecurityAwareTrait;

    protected function provideCollection(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): array|object {
        $criteria = [];
        if (null !== $workspaceId = self::getParameterId($operation, 'workspaceId')) {
            $criteria['workspace'] = $workspaceId;
        }

        $policies = $this->em->getRepository(RenditionPolicy::class)->findBy($criteria);

        return array_filter($policies, fn (RenditionPolicy $renditionPolicy): bool => $this->security->isGranted(AbstractVoter::READ, $renditionPolicy));
    }
}
