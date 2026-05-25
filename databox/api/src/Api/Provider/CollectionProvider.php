<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use App\Elasticsearch\CollectionSearch;
use App\Repository\Core\CollectionRepository;
use App\Repository\Core\WorkspaceRepository;

class CollectionProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly CollectionSearch $search,
        private readonly CollectionRepository $collectionRepository,
        private readonly WorkspaceRepository $workspaceRepository,
    ) {
    }

    protected function provideCollection(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): array|object {
        $filters = $context['filters'] ?? [];

        if (
            empty($filters['parent'])
            && empty($filters['parents'])
            && empty($filters['query'])
        ) {
            if ($context['userId']) {
                $allowedWorkspaces = $this->workspaceRepository->getAllowedWorkspaceIds($context['userId'], $context['groupIds']);
            } else {
                $allowedWorkspaces = $this->workspaceRepository->getPublicWorkspaceIds();
            }
            if (!empty($filters['workspaces'])) {
                $allowedWorkspaces = array_intersect($allowedWorkspaces, $filters['workspaces']);
            }

            return $this->collectionRepository->getRootCollections($allowedWorkspaces, $context['userId'], $context['groupIds']);
        }

        $result = $this->search->search($context['userId'], $context['groupIds'], $filters);

        return new PagerFantaApiPlatformPaginator($result);
    }
}
