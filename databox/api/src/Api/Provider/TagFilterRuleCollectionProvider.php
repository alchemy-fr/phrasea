<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Entity\Core\TagFilterRule;

class TagFilterRuleCollectionProvider extends AbstractCollectionProvider
{
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

        return $this->em->getRepository(TagFilterRule::class)->findBy($criteria);
    }
}
