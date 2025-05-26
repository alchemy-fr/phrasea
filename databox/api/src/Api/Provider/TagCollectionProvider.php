<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use App\Api\EntityIriConverter;
use App\Api\Traits\CollectionProviderAwareTrait;
use App\Api\Traits\WorkspaceCollectionTrait;
use App\Elasticsearch\TagSearch;

final class TagCollectionProvider extends AbstractCollectionProvider
{
    use CollectionProviderAwareTrait;
    use SecurityAwareTrait;
    use WorkspaceCollectionTrait;

    public function __construct(
        private readonly TagSearch $tagSearch,
        private readonly EntityIriConverter $entityIriConverter,
    ) {
    }

    protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array|object
    {
        $workspaces = $this->resolveAllowedWorkspaces($context);
        if (empty($workspaces)) {
            return [];
        }

        $queryString = $context['filters']['query'] ?? null;

        if (!empty($queryString)) {
            return $this->tagSearch->search($workspaces, $context['filters']);
        }

        return $this->collectionProvider->provide($operation, $uriVariables, $context);
    }
}
