<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use App\Entity\Core\RenditionPolicy;
use App\Security\Voter\AbstractVoter;

class RenditionPolicyCollectionProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;

    protected function provideCollection(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): array|object {
        $criteria = [];
        $filters = $context['filters'] ?? [];

        if (isset($filters['workspaceId'])) {
            $criteria['workspace'] = $filters['workspaceId'];
        }

        $policies = $this->em->getRepository(RenditionPolicy::class)->findBy($criteria);

        return array_filter($policies, fn (RenditionPolicy $renditionPolicy): bool => $this->security->isGranted(AbstractVoter::READ, $renditionPolicy));
    }
}
