<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use App\Api\EntityIriConverter;
use App\Api\Traits\CollectionProviderAwareTrait;
use App\Api\Traits\WorkspaceCollectionTrait;
use App\Elasticsearch\AttributeEntitySearch;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class AttributeEntityCollectionProvider extends AbstractCollectionProvider
{
    use CollectionProviderAwareTrait;
    use SecurityAwareTrait;
    use WorkspaceCollectionTrait;

    public function __construct(
        private readonly AttributeEntitySearch $search,
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
            return $this->search->search($workspaces, $context['filters'] ?? []);
        }

        return $this->collectionProvider->provide($operation, $uriVariables, $context);
    }
}
