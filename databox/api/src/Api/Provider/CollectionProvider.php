<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use App\Api\Traits\ParameterValuesTrait;
use App\Elasticsearch\AbstractSearch;
use App\Elasticsearch\CollectionSearch;
use App\Elasticsearch\NoWorkspaceAllowedException;
use App\Repository\Core\CollectionRepository;
use App\Repository\Core\WorkspaceRepository;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\CollectionVoter;

class CollectionProvider extends AbstractCollectionProvider
{
    use ParameterValuesTrait;
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
        $filters = self::getParameterValues($operation);

        if (
            empty($filters['parent'])
            && empty($filters['parents'])
            && empty($filters['query'])
            && !$this->hasScope(AbstractVoter::LIST, CollectionVoter::SCOPE_PREFIX)
            && !$this->isAdmin()
        ) {
            if ($context['userId']) {
                $allowedWorkspaces = $this->workspaceRepository->getAllowedWorkspaceIds($context['userId'], $context['groupIds'], $this->isAdmin());
            } else {
                $allowedWorkspaces = $this->workspaceRepository->getPublicWorkspaceIds();
            }
            if (!empty($workspaceIds = AbstractSearch::toIdList($filters['workspaces'] ?? null))) {
                $allowedWorkspaces = array_values(array_intersect($allowedWorkspaces, $workspaceIds));
            }

            return $this->collectionRepository->getRootCollections($allowedWorkspaces, $context['userId'], $context['groupIds']);
        }

        try {
            $result = $this->search->search($context['userId'], $context['groupIds'], $filters, $operation);
        } catch (NoWorkspaceAllowedException) {
            return [];
        }

        return new PagerFantaApiPlatformPaginator($result);
    }
}
