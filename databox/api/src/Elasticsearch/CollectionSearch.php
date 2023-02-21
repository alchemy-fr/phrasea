<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Aggregation;
use Elastica\Query;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use Pagerfanta\Pagerfanta;

class CollectionSearch extends AbstractSearch
{
    private PaginatedFinderInterface $finder;
    private EntityManagerInterface $em;

    public function __construct(
        PaginatedFinderInterface $finder,
        EntityManagerInterface $em
    ) {
        $this->finder = $finder;
        $this->em = $em;
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
        $query->setTrackTotalHits(true);
        $query->setSort([
            'sortName' => ['order' => 'asc'],
        ]);

        $data = $this->finder->findPaginated($query);
        $data->setMaxPerPage($limit);
        $data->setCurrentPage($options['page'] ?? 1);

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

        $maxLimit = 30;
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
            $boolQuery->addFilter(new Query\Term(['pathDepth' => 0]));
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

    private function findCollection(string $id): Collection
    {
        return $this->em->find(Collection::class, $id);
    }

    /**
     * @return Workspace[]
     */
    private function findWorkspaces(array $ids): array
    {
        return $this->findEntityByIds(Workspace::class, $ids);
    }

    private function findEntityByIds(string $entityName, array $ids): array
    {
        return $this->em
            ->createQueryBuilder()
            ->select('t')
            ->from($entityName, 't')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }
}
