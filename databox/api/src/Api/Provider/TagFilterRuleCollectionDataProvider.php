<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Entity\Core\TagFilterRule;

class TagFilterRuleCollectionDataProvider extends AbstractCollectionProvider
{
    protected function provideCollection(
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): array|object {
        $criteria = [];
        $filters = $context['filters'] ?? [];
        if (isset($filters['collectionId'])) {
            $criteria['objectType'] = TagFilterRule::TYPE_COLLECTION;
            $criteria['objectId'] = $filters['collectionId'];
        }
        if (isset($filters['workspaceId'])) {
            $criteria['objectType'] = TagFilterRule::TYPE_WORKSPACE;
            $criteria['objectId'] = $filters['workspaceId'];
        }

        return $this->em->getRepository(TagFilterRule::class)->findBy($criteria);
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return TagFilterRule::class === $resourceClass;
    }
}
