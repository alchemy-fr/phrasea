<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use App\Elasticsearch\Filter\SearchQuery;
use App\Entity\Core\Collection;
use App\Repository\Core\CollectionRepository;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\CollectionVoter;
use Elastica\Query;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use Pagerfanta\Pagerfanta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CollectionSearch extends AbstractSearch
{
    public function __construct(
        #[Autowire(service: 'fos_elastica.finder.collection')]
        private readonly PaginatedFinderInterface $finder,
        private readonly QueryStringParser $queryStringParser,
        private readonly CollectionRepository $collectionRepository,
    ) {
    }

    public function search(
        ?string $userId,
        array $groupIds,
        array $options = [],
        ?Operation $operation = null,
    ): Pagerfanta {
        $maxLimit = 50;

        $filterQuery = new Query\BoolQuery();
        $this->applyFilters($filterQuery, $userId, $groupIds, $options);

        $searchQuery = new SearchQuery($filterQuery);
        $this->applyParameters($searchQuery, Collection::class, $operation, $options);

        $query = new Query();
        $query->setQuery($filterQuery);
        $query->setTrackTotalHits();
        $query->setSort($searchQuery->hasSort() ? $searchQuery->getSort() : [
            'sortName' => ['order' => 'asc'],
        ]);

        if (!empty($options['query'])) {
            $query->setHighlight([
                'pre_tags' => ['[hl]'],
                'post_tags' => ['[/hl]'],
                'fields' => [
                    'name' => [
                        'fragment_size' => 255,
                        'number_of_fragments' => 1,
                    ],
                ],
            ]);
        }

        $data = $this->finder->findPaginated($query);
        self::applyPagination($data, $options, $maxLimit);
        $this->executeSearch($data->getCurrentPageResults(...));

        return $data;
    }

    private function applyFilters(
        Query\BoolQuery $boolQuery,
        ?string $userId,
        array $groupIds,
        array $options = [],
    ): void {
        $aclBoolQuery = $this->createACLBoolQuery($userId, $groupIds);

        $queryString = trim((string) ($options['query'] ?? ''));
        $parsed = $this->queryStringParser->parseQuery($queryString);
        // "deep" is a boolean parameter on the API; callers from PHP may pass any truthy/falsy value
        $deep = filter_var($options['deep'] ?? !empty($queryString), FILTER_VALIDATE_BOOLEAN);

        if (!empty($parsed['should'])) {
            $searchBool = new Query\BoolQuery();
            $searchBool->addShould(new Query\MatchQuery('name', $parsed['should']));
            $boolQuery->addMust($searchBool);
        }
        foreach ($parsed['must'] as $must) {
            $boolQuery->addMust(new Query\MatchQuery('name', $must));
        }

        $includeDeleted = false;
        foreach ($parsed['filters'] as $filter) {
            if (isset($filter['in'])) {
                switch ($filter['in']) {
                    case 'all':
                        $includeDeleted = true;
                        break;
                    case 'trash':
                        $boolQuery->addFilter(new Query\Term(['deleted' => true]));
                        $includeDeleted = true;
                        break;
                }
            }
        }

        if (!$includeDeleted) {
            $boolQuery->addFilter(new Query\Term(['deleted' => false]));
        }

        if (null !== $aclBoolQuery) {
            $boolQuery->addFilter($aclBoolQuery);
        }

        if (!empty($options['parent'])) {
            $options['parents'] = [$options['parent']];
        }

        if (!empty($parentIds = self::toIdList($options['parents'] ?? null))) {
            $parentCollections = DoctrineUtil::getFromIds($this->collectionRepository, $parentIds);
            $parentsBoolQuery = new Query\BoolQuery();
            array_map(function (Collection $parentCollection) use ($parentsBoolQuery, $deep): void {
                $q = new Query\BoolQuery();
                $q->addFilter(new Query\Term(['absolutePath' => $parentCollection->getAbsolutePath()]));

                if (!$deep) {
                    $q->addFilter(new Query\Term(['pathDepth' => $parentCollection->getPathDepth() + 1]));
                } else {
                    $q->addFilter(new Query\Range('pathDepth', ['gte' => $parentCollection->getPathDepth() + 1]));
                }
                $parentsBoolQuery->addMust($q);
            }, $parentCollections);

            $boolQuery->addFilter($parentsBoolQuery);
        } else {
            $boolQuery->addFilter(new Query\Term(['pathDepth' => 0]));
        }

        if (!empty($workspaceIds = self::toIdList($options['workspaces'] ?? null))) {
            $boolQuery->addFilter(
                new Query\Terms('workspaceId', $workspaceIds)
            );
        }
    }

    protected function getAdminScope(): ?string
    {
        return CollectionVoter::SCOPE_PREFIX.AbstractVoter::LIST;
    }
}
