<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use App\Api\Traits\ParameterValuesTrait;
use App\Elasticsearch\AbstractSearch;
use App\Elasticsearch\CollectionSearch;
use App\Elasticsearch\NoWorkspaceAllowedException;
use App\Entity\Core\Collection;
use App\Repository\Core\CollectionRepository;
use App\Repository\Core\WorkspaceRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Collection\CollectionSorter;

class CollectionProvider extends AbstractCollectionProvider
{
    use ParameterValuesTrait;
    use SecurityAwareTrait;

    public function __construct(
        private readonly CollectionSearch $search,
        private readonly CollectionRepository $collectionRepository,
        private readonly WorkspaceRepository $workspaceRepository,
        private readonly CollectionSorter $collectionSorter,
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
            && !$this->isGranted(AbstractVoter::LIST, new Collection())
        ) {
            if ($context['userId']) {
                $allowedWorkspaces = $this->workspaceRepository->getReadableWorkspaceIds($context['userId'], $context['groupIds'], $this->isAdmin());
            } else {
                $allowedWorkspaces = $this->workspaceRepository->getPublicWorkspaceIds();
            }
            if (!empty($workspaceIds = AbstractSearch::toIdList($filters['workspaces'] ?? null))) {
                $allowedWorkspaces = array_values(array_intersect($allowedWorkspaces, $workspaceIds));
            }

            // The voter has the last word: a stale collection_access row must not expose a collection
            $collections = array_values(array_filter(
                $this->collectionRepository->getRootCollections($allowedWorkspaces, $context['userId'], $context['groupIds']),
                fn (Collection $collection): bool => $this->isGranted(AbstractVoter::READ, $collection),
            ));

            return $this->collectionSorter->sort($collections);
        }

        try {
            $result = $this->search->search($context['userId'], $context['groupIds'], $filters, $operation);
        } catch (NoWorkspaceAllowedException) {
            return [];
        }

        return new PagerFantaApiPlatformPaginator($result);
    }
}
