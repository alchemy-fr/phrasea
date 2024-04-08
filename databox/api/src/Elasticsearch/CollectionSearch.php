<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Entity\Core\Collection;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Elastica\Aggregation;
use Elastica\Query;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use Pagerfanta\Pagerfanta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CollectionSearch extends AbstractSearch
{
    public function __construct(
        #[Autowire(service: 'fos_elastica.finder.collection')]
        private readonly PaginatedFinderInterface $finder
    ) {
    }

    public function search(
        ?string $userId,
        array $groupIds,
        array $options = []
    ): Pagerfanta {
        $maxLimit = 50;
        $limit = $options['limit'] ?? $maxLimit;
        if ($limit > $maxLimit) {
            $limit = $maxLimit;
        }

        $filterQuery = new Query\BoolQuery();
        $this->applyFilters($filterQuery, $userId, $groupIds, $options);

        $query = new Query();
        $query->setQuery($filterQuery);
        $query->setTrackTotalHits();
        $query->setSort([
            'sortName' => ['order' => 'asc'],
        ]);

        $data = $this->finder->findPaginated($query);
        $data->setMaxPerPage((int) $limit);
        $data->setCurrentPage((int) ($options['page'] ?? 1));

        return $data;
    }

    public function searchAggregationsByWorkspace(
        ?string $userId,
        array $groupIds,
        array $options = []
    ): array {
        $query = new Query();
        $query->setSize(0);

        $boolQuery = new Query\BoolQuery();

        $this->applyFilters($boolQuery, $userId, $groupIds, $options);

        $aggregation = new Aggregation\Filter('ws');
        $query->addAggregation($aggregation);
        $aggregation->setFilter($boolQuery);

        $termAgg = new Aggregation\Terms('workspaceId');
        $termAgg->setField('workspaceId');
        $termAgg->setSize(300);

        $aggregation->addAggregation($termAgg);

        $maxLimit = 50;
        $limit = (int) ($options['limit'] ?? $maxLimit);
        if ($limit > $maxLimit) {
            $limit = $maxLimit;
        }
        $top = new Aggregation\TopHits('top');
        $top->setSize($limit);
        $top->setSort([
            'sortName' => ['order' => 'asc'],
        ]);
        $termAgg->addAggregation($top);

        $result = $this->finder->findPaginated($query);
        $aggregations = $result->getAdapter()->getAggregations();

        $data = [];
        foreach ($aggregations['ws']['workspaceId']['buckets'] as $bucket) {
            foreach ($bucket['top']['hits']['hits'] as $hit) {
                $object = $this->em->find(Collection::class, $hit['_id']);

                if ($object instanceof Collection) {
                    $data[] = $object;
                }
            }
        }

        return $data;
    }

    private function applyFilters(
        Query\BoolQuery $boolQuery,
        ?string $userId,
        array $groupIds,
        array $options = []): void
    {
        $aclBoolQuery = $this->createACLBoolQuery($userId, $groupIds);

        if (null !== $aclBoolQuery) {
            $boolQuery->addFilter($aclBoolQuery);
        }

        if (isset($options['parent'])) {
            $options['parents'] = [$options['parent']];
        }
        if (isset($options['parents'])) {
            $parentCollections = $this->findCollections($options['parents']);
            $parentsBoolQuery = new Query\BoolQuery();
            array_map(function (Collection $parentCollection) use ($parentsBoolQuery): void {
                $q = new Query\BoolQuery();
                $q->addFilter(new Query\Term(['absolutePath' => $parentCollection->getAbsolutePath()]));
                $q->addFilter(new Query\Term(['pathDepth' => $parentCollection->getPathDepth() + 1]));
                $parentsBoolQuery->addMust($q);
            }, $parentCollections);

            $boolQuery->addFilter($parentsBoolQuery);
        } else {
            $rootLevelQuery = new Query\BoolQuery();
            $rootLevelQuery->addShould(new Query\Term(['pathDepth' => 0]));
            if (null !== $userId) {
                $rootLevelQuery->addShould(new Query\Term(['nlUsers' => $userId]));
                if (!empty($groupIds)) {
                    $rootLevelQuery->addShould(new Query\Terms('nlGroups', $groupIds));
                }
            }

            $publicWorkspaceIds = $this->getPublicWorkspaceIds();
            if (!empty($publicWorkspaceIds)) {
                $b = new Query\BoolQuery();
                $b->addMust(new Query\Terms('workspaceId', $publicWorkspaceIds));
                $b->addMust(new Query\Term(['privacyRoots' => WorkspaceItemPrivacyInterface::PUBLIC]));
                $rootLevelQuery->addShould($b);
            }

            if (null !== $userId) {
                $allowedWorkspaceIds = $this->getAllowedWorkspaceIds($userId, $groupIds);
                if (!empty($allowedWorkspaceIds)) {
                    $b = new Query\BoolQuery();
                    $b->addMust(new Query\Terms('workspaceId', $allowedWorkspaceIds));
                    $b->addMust(new Query\Terms('privacyRoots', [
                        WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE,
                        WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE,
                        WorkspaceItemPrivacyInterface::PRIVATE,
                        WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS,
                        WorkspaceItemPrivacyInterface::PUBLIC,
                    ]));
                    $rootLevelQuery->addShould($b);
                }
            }

            $boolQuery->addFilter($rootLevelQuery);
        }

        if (isset($options['workspaces'])) {
            $boolQuery->addFilter(
                new Query\Terms('workspaceId', $options['workspaces'])
            );
        }
    }

    /**
     * @return Collection[]
     */
    private function findCollections(array $ids): array
    {
        return $this->findEntityByIds(Collection::class, $ids);
    }
}
