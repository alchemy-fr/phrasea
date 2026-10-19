<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Attribute\AttributeInterface;
use App\Attribute\Type\GeoPointAttributeType;
use App\Elasticsearch\AQL\ConditionOperatorEnum;
use App\Elasticsearch\BuiltInAttribute\AssetStatusBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\BuiltInAttributeRegistry;
use App\Elasticsearch\BuiltInAttribute\CollectionBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\CustomSortBuiltInAttributeInterface;
use App\Elasticsearch\BuiltInAttribute\DeletedBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\DirectCollectionBuiltInAttribute;
use App\Elasticsearch\Geo\GeoCluster;
use App\Elasticsearch\Geo\GeoClusterResult;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\Collection;
use App\Entity\SavedSearch\SavedSearch;
use App\Repository\Core\CollectionRepository;
use App\Repository\SavedSearch\SavedSearchRepository;
use App\Security\AttributeFilterManager;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\AssetVoter;
use App\Service\Asset\AssetSortGroupMapper;
use Elastica\Aggregation;
use Elastica\Index;
use Elastica\Query;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\Paginator\FantaPaginatorAdapter;
use Pagerfanta\Pagerfanta;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssetSearch extends AbstractSearch
{
    final public const int MAX_GEO_CLUSTERS = 2000;

    /**
     * Marks an order resolved from a saved search, whose fields may have disappeared since it was saved.
     */
    private const string OPT_SAVED_SORT = '_savedSearchSort';

    public function __construct(
        #[Autowire(service: 'fos_elastica.finder.asset')]
        private readonly PaginatedFinderInterface $finder,
        #[Autowire(service: 'fos_elastica.index.asset')]
        private readonly Index $index,
        private readonly AttributeFilterManager $attributeFilterManager,
        private readonly LoggerInterface $logger,
        private readonly AttributeSearch $attributeSearch,
        private readonly QueryStringParser $queryStringParser,
        private readonly FacetHandler $facetHandler,
        private readonly DeletedBuiltInAttribute $deletedBuiltInAttribute,
        private readonly AssetStatusBuiltInAttribute $assetStatusBuiltInAttribute,
        private readonly CollectionRepository $collectionRepository,
        private readonly SavedSearchRepository $savedSearchRepository,
        private readonly AssetSortGroupMapper $assetSortGroupMapper,
        private readonly BuiltInAttributeRegistry $builtInAttributeRegistry,
    ) {
    }

    public function search(
        ?string $userId,
        array $groupIds,
        array $options = [],
    ): array {
        [$query, $limit, $options] = $this->buildQuery($userId, $groupIds, $options);

        $this->applySort($query, $options);

        $query->setHighlight([
            'pre_tags' => ['[hl]'],
            'post_tags' => ['[/hl]'],
            'fields' => [
                'name' => [
                    'fragment_size' => 255,
                    'number_of_fragments' => 1,
                ],
                AttributeInterface::ATTRIBUTES_FIELD.'.*' => [
                    'number_of_fragments' => 20,
                ],
            ],
        ]);

        $this->facetHandler->addBuiltInFacets($query);
        $this->attributeSearch->buildFacets($query, $userId, $groupIds, $options);

        /** @var FantaPaginatorAdapter $adapter */
        $adapter = $this->finder->findPaginated($query)->getAdapter();
        $result = new Pagerfanta(new FilteredPager(fn (Asset $asset,
        ): bool => $this->isGranted(AbstractVoter::READ, $asset), $adapter));
        $result->setMaxPerPage((int) $limit);
        if ($options['page'] ?? false) {
            $result->setAllowOutOfRangePages(true);
            $result->setCurrentPage((int) $options['page']);
        }
        $start = microtime(true);
        // Force query to ensure adapter will run it just once.
        $this->executeSearch($result->getCurrentPageResults(...));
        $searchTime = microtime(true) - $start;

        $facets = $adapter->getAggregations();
        $facets = $this->facetHandler->normalizeBuckets($facets);

        $esQuery = $query->toArray();

        $groupByKey = $options['group'][0] ?? null;
        if (null !== $groupByKey) {
            $this->assetSortGroupMapper->resolveSortGroups($result, $groupByKey);
        }

        return [$result, $facets, $esQuery, $searchTime];
    }

    /**
     * Clusters the geolocated assets matching the search on a tile grid.
     *
     * @param string                                                           $field     the geo point attribute key (e.g. "location_geo-point_s")
     * @param int                                                              $precision the tile zoom level (0-29)
     * @param array{top: float, left: float, bottom: float, right: float}|null $bounds    the area to cluster, the whole world when null
     */
    public function geoClusters(
        ?string $userId,
        array $groupIds,
        string $field,
        int $precision,
        ?array $bounds,
        array $options = [],
    ): GeoClusterResult {
        try {
            $fieldInfo = $this->attributeSearch->getESFieldInfo($field);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException(sprintf('Invalid geo field "%s"', $field), $e);
        }
        if ($fieldInfo->builtIn || GeoPointAttributeType::getName() !== $fieldInfo->type::getName()) {
            throw new BadRequestHttpException(sprintf('Field "%s" is not a geo point attribute', $field));
        }

        [$query] = $this->buildQuery($userId, $groupIds, $options);
        $query->setSize(0);

        $geoFilter = new Query\BoolQuery();
        $geoFilter->addFilter(new Query\Exists($fieldInfo->name));
        if (null !== $workspaceRestriction = $this->createFieldWorkspaceRestriction($userId, $groupIds, $fieldInfo->name)) {
            $geoFilter->addFilter($workspaceRestriction);
        }

        $tiles = new Aggregation\GeotileGridAggregation('tiles', $fieldInfo->name);
        $tiles->setPrecision(max(0, min(29, $precision)));
        $tiles->setSize(self::MAX_GEO_CLUSTERS);
        if (null !== $bounds) {
            $tiles->setParam('bounds', [
                'top_left' => ['lat' => $bounds['top'], 'lon' => $bounds['left']],
                'bottom_right' => ['lat' => $bounds['bottom'], 'lon' => $bounds['right']],
            ]);
        }
        $tiles->addAggregation(new Aggregation\GeoCentroid('centroid', $fieldInfo->name));
        $tiles->addAggregation(new Aggregation\GeoBounds('bounds', $fieldInfo->name));
        $sample = new Aggregation\TopHits('sample');
        $sample->setSize(1);
        $sample->setSource(false);
        $tiles->addAggregation($sample);

        $geo = new Aggregation\Filter('geo', $geoFilter);
        $geo->addAggregation(new Aggregation\GeoBounds('bounds', $fieldInfo->name));
        $geo->addAggregation($tiles);
        $query->addAggregation($geo);

        $resultSet = $this->index->search($query);
        $geoAgg = $resultSet->getAggregation('geo');

        $clusters = [];
        foreach ($geoAgg['tiles']['buckets'] as $bucket) {
            $clusters[] = new GeoCluster(
                key: $bucket['key'],
                count: $bucket['doc_count'],
                lat: $bucket['centroid']['location']['lat'],
                lng: $bucket['centroid']['location']['lon'],
                bounds: self::normalizeGeoBounds($bucket['bounds']['bounds']),
                assetId: 1 === $bucket['doc_count'] ? ($bucket['sample']['hits']['hits'][0]['_id'] ?? null) : null,
            );
        }

        return new GeoClusterResult(
            total: $resultSet->getTotalHits(),
            geolocated: $geoAgg['doc_count'],
            bounds: isset($geoAgg['bounds']['bounds']) ? self::normalizeGeoBounds($geoAgg['bounds']['bounds']) : null,
            clusters: $clusters,
        );
    }

    /**
     * @return array{top: float, left: float, bottom: float, right: float}
     */
    private static function normalizeGeoBounds(array $bounds): array
    {
        return [
            'top' => $bounds['top_left']['lat'],
            'left' => $bounds['top_left']['lon'],
            'bottom' => $bounds['bottom_right']['lat'],
            'right' => $bounds['bottom_right']['lon'],
        ];
    }

    /**
     * Restricts an attribute field to the workspaces where the user may read it.
     * Null when the user may read it everywhere.
     */
    private function createFieldWorkspaceRestriction(?string $userId, array $groupIds, string $fieldName): ?Query\AbstractQuery
    {
        $workspaceIds = [];
        foreach ($this->attributeSearch->buildSearchableAttributeDefinitionsGroups($userId, $groupIds) as $cluster) {
            if (!isset($cluster['fields'][$fieldName])) {
                continue;
            }
            if (empty($cluster['w'])) {
                return null;
            }
            array_push($workspaceIds, ...$cluster['w']);
        }

        if (empty($workspaceIds)) {
            return new Query\MatchNone();
        }

        return new Query\Terms('workspaceId', array_values(array_unique($workspaceIds)));
    }

    /**
     * The filtered query shared by the asset searches: ACL, conditions and full-text query.
     *
     * @return array{Query, int, array} the query, the page size and the resolved options
     */
    private function buildQuery(
        ?string $userId,
        array $groupIds,
        array $options,
    ): array {
        $maxLimit = 50;
        $options['userId'] = $userId;
        $options['groupIds'] = $groupIds;

        if (isset($options['savedSearch'])) {
            $options = $this->applySavedSearch($options);
        }

        $filterQueries = [];

        if (isset($options['parent'])) {
            $options['parents'] = [$options['parent']];
        }

        if (isset($options['story'])) {
            $filterQueries[] = new Query\Term(['stories' => $options['story']]);
        }

        if (isset($options['collection'])) {
            // Exact membership: unlike "parents", this does not span the sub-tree.
            // collectionPaths is path-hierarchy analyzed, its "raw" sub-field holds the full paths.
            $collection = DoctrineUtil::findStrictByRepo($this->collectionRepository, $options['collection'], throw404: true);
            $filterQueries[] = new Query\Term(['collectionPaths.raw' => $collection->getAbsolutePath()]);
        }

        if (!empty($parentIds = self::toIdList($options['parents'] ?? null))) {
            $paths = $this->resolveCollectionPaths($parentIds);

            $filterQueries[] = new Query\Terms(CollectionBuiltInAttribute::getName(), $paths);
        }

        if (isset($options['directCollections'])) {
            $filterQueries[] = new Query\Terms(
                DirectCollectionBuiltInAttribute::getName(),
                $this->resolveCollectionPaths($options['directCollections'])
            );
        }

        if (!empty($assetIds = self::toIdList($options['ids'] ?? null))) {
            $filterQueries[] = new Query\Terms('_id', $assetIds);
            $maxLimit = 500;
        }

        if (!empty($workspaceIds = self::toIdList($options['workspaces'] ?? null))) {
            $filterQueries[] = new Query\Terms('workspaceId', $workspaceIds);
        }

        if (isset($options['tags_must']) || isset($options['tags_must_not'])) {
            $tagsBoolQuery = new Query\BoolQuery();
            $filterQueries[] = $tagsBoolQuery;

            if (isset($options['tags_must'])) {
                foreach ($options['tags_must'] as $tag) {
                    $tagsBoolQuery->addMust(new Query\Term(['tags' => $tag]));
                }
            }

            if (isset($options['tags_must_not'])) {
                foreach ($options['tags_must_not'] as $tag) {
                    $tagsBoolQuery->addMustNot(new Query\Term(['tags' => $tag]));
                }
            }
        }

        $limit = $options['limit'] ?? $maxLimit;
        if ($limit > $maxLimit) {
            $limit = $maxLimit;
        }

        $hasDeletedFilter = false;
        $hasStatusFilter = false;
        if (null !== $conditions = ($options['conditions'] ?? null)) {
            foreach ($conditions as $condition) {
                if (is_array($condition)) {
                    if ($condition['disabled'] ?? false) {
                        continue;
                    }
                    $query = $condition['query'] ?? '';
                    $condition = ($condition['inversed'] ?? false) ? sprintf('NOT (%s)', $query) : $query;
                }

                // An inverted condition still targets the attribute it negates
                $subject = (string) preg_replace('/^(?:NOT\b\s*|\(\s*)+/', '', (string) $condition);
                if (str_starts_with($subject, DeletedBuiltInAttribute::getKey())) {
                    $hasDeletedFilter = true;
                }
                if (str_starts_with($subject, AssetStatusBuiltInAttribute::getKey())) {
                    $hasStatusFilter = true;
                }
                $filterQueries[] = $this->attributeSearch->buildConditionQuery(
                    $this->attributeSearch->buildSearchableAttributeDefinitionsGroups($userId, $groupIds),
                    $condition,
                    $options
                );
            }
        }

        $filterQueries = array_merge($this->createBaseFilterQueries(
            $userId,
            $groupIds,
            $options,
            filterDeleted: !$hasDeletedFilter,
            filterStatus: !$hasStatusFilter && !$hasDeletedFilter,
        ), $filterQueries);

        $filterQuery = new Query\BoolQuery();
        foreach ($filterQueries as $query) {
            $filterQuery->addFilter($query);
        }

        $queryString = trim($options['query'] ?? '');
        $parsed = $this->queryStringParser->parseQuery($queryString);

        if (!empty($parsed['should'])) {
            $multiMatch = $this->attributeSearch->buildAttributeQuery(
                $this->attributeSearch->buildSearchableAttributeDefinitionsGroups($userId, $groupIds), $parsed['should'], $options);
            $filterQuery->addMust($multiMatch);
        }
        foreach ($parsed['must'] as $must) {
            $multiMatch = $this->attributeSearch->buildAttributeQuery(
                $this->attributeSearch->buildSearchableAttributeDefinitionsGroups($userId, $groupIds), $must, array_merge($options, [
                    AttributeSearch::OPT_STRICT_PHRASE => true,
                ]));
            $filterQuery->addMust($multiMatch);
        }

        $query = new Query();
        $query->setTrackTotalHits();
        $query->setQuery($filterQuery);

        return [$query, (int) $limit, $options];
    }

    /**
     * Filters shared by every search on the asset index: ACL, tag filter rules and, unless the
     * caller filters on them itself, "not deleted" and "accepted" status.
     * When the caller does filter on them, the deleted and quarantined assets stay restricted
     * to the users the AssetVoter lets read them: the caller's conditions cannot widen that.
     *
     * @return Query\AbstractQuery[]
     *
     * @throws NoWorkspaceAllowedException
     */
    public function createBaseFilterQueries(
        ?string $userId,
        array $groupIds,
        array $options = [],
        bool $filterDeleted = true,
        bool $filterStatus = true,
    ): array {
        $options['userId'] = $userId;
        $options['groupIds'] = $groupIds;

        $filterQueries = [];
        if (null !== $aclBoolQuery = $this->createACLBoolQuery($userId, $groupIds)) {
            $filterQueries[] = $aclBoolQuery;
        }

        if (null !== $attrFilterQuery = $this->buildAttributeFilterQuery($userId, $groupIds, $options)) {
            $filterQueries[] = $attrFilterQuery;
        }
        $filterQueries[] = $this->deletedBuiltInAttribute->createFilterQuery($filterDeleted ? false : null, ConditionOperatorEnum::EQUALS, $options);
        $filterQueries[] = $filterStatus
            ? $this->assetStatusBuiltInAttribute->createFilterQuery(AssetStatusEnum::Accepted, ConditionOperatorEnum::EQUALS, $options)
            : $this->assetStatusBuiltInAttribute->createVisibleFilterQuery($options);

        return $filterQueries;
    }

    /**
     * @param string|string[] $collectionIds
     *
     * @return string[] the absolute path of each requested collection
     */
    private function resolveCollectionPaths(array|string $collectionIds): array
    {
        $collections = DoctrineUtil::getFromIds($this->collectionRepository, self::toIdList($collectionIds));
        $paths = array_map(fn (Collection $collection): string => $collection->getAbsolutePath(), $collections);

        if (empty($paths)) {
            throw new NotFoundHttpException('Collections not found');
        }

        return $paths;
    }

    private function buildAttributeFilterQuery(?string $userId, array $groupIds, array $options): ?Query\BoolQuery
    {
        $ruleSet = $this->attributeFilterManager->getUserRules($userId, $groupIds);
        if (empty($ruleSet)) {
            return null;
        }

        $attributeGroups = $this->attributeSearch->buildAllAttributeDefinitionsGroups();
        $query = new Query\BoolQuery();
        $hasConditions = false;

        foreach ($ruleSet as $wId => $conditions) {
            foreach ($conditions as $condition) {
                try {
                    $conditionQuery = $this->attributeSearch->buildConditionQuery(
                        $attributeGroups,
                        $condition,
                        $options
                    );
                } catch (\Throwable $e) {
                    // Fail-closed: hide the whole workspace rather than breaking search or leaking assets
                    $this->logger->error('Invalid attribute filter rule condition', [
                        'workspaceId' => $wId,
                        'condition' => $condition,
                        'error' => $e->getMessage(),
                    ]);
                    $conditionQuery = new Query\MatchNone();
                }

                $scoped = new Query\BoolQuery();
                $notWorkspace = new Query\BoolQuery();
                $notWorkspace->addMustNot(new Query\Term(['workspaceId' => $wId]));
                $scoped->addShould($notWorkspace);
                $scoped->addShould($conditionQuery);

                $query->addMust($scoped);
                $hasConditions = true;
            }
        }

        if (!$hasConditions) {
            return null;
        }

        return $query;
    }

    /**
     * Applies a saved search as the base of the search: the request refines it but cannot widen it.
     * Only the query, conditions and sort of its data are used.
     */
    private function applySavedSearch(array $options): array
    {
        /** @var SavedSearch $savedSearch */
        $savedSearch = DoctrineUtil::findStrictByRepo($this->savedSearchRepository, $options['savedSearch'], throw404: true);
        $this->denyAccessUnlessGranted(AbstractVoter::READ, $savedSearch);

        $data = $savedSearch->getData();

        if ('' === trim($options['query'] ?? '') && is_string($data['query'] ?? null)) {
            $options['query'] = $data['query'];
        }

        $conditions = array_merge(
            array_values((array) ($data['conditions'] ?? [])),
            array_values((array) ($options['conditions'] ?? [])),
        );
        if (!empty($conditions)) {
            $options['conditions'] = $conditions;
        }

        if (!isset($options['order']) && is_array($data['sortBy'] ?? null)) {
            $order = [];
            $group = null;
            foreach ($data['sortBy'] as $sortBy) {
                if (!is_array($sortBy) || !is_string($sortBy['a'] ?? null)) {
                    continue;
                }
                $order[$sortBy['a']] = 1 === ($sortBy['w'] ?? 0) ? 'desc' : 'asc';
                if (null === $group && ($sortBy['g'] ?? false)) {
                    $group = $sortBy['a'];
                }
            }

            if (!empty($order)) {
                $options['order'] = $order;
                $options[self::OPT_SAVED_SORT] = true;
                if (null !== $group && !isset($options['group']) && $this->isKnownField($group)) {
                    $options['group'] = [$group];
                }
            }
        }

        return $options;
    }

    private function isKnownField(string $field): bool
    {
        try {
            $this->attributeSearch->getESFieldInfo($field);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    private function applySort(Query $query, array $options): void
    {
        // A saved search must stay usable once a field it sorts on is gone: its invalid fields are skipped
        $lenient = $options[self::OPT_SAVED_SORT] ?? false;
        $sort = [];
        if (isset($options['order'])) {
            if (!is_array($options['order'])) {
                throw new BadRequestHttpException('Invalid sort: expected order[<field>]=asc|desc');
            }
            foreach ($options['order'] as $field => $way) {
                if (!is_string($field)) {
                    if ($lenient) {
                        continue;
                    }
                    throw new BadRequestHttpException(sprintf('Invalid sort field "%s"', $field));
                }
                $w = strtoupper((string) $way);
                if (!in_array($w, ['ASC', 'DESC'], true)) {
                    if ($lenient) {
                        continue;
                    }
                    throw new BadRequestHttpException(sprintf('Invalid sort way "%s"', $way));
                }

                $builtInAttribute = $this->builtInAttributeRegistry->getBuiltInAttribute($field);
                if ($builtInAttribute instanceof CustomSortBuiltInAttributeInterface) {
                    try {
                        $sort[] = $builtInAttribute->createSortClause($w, $options);
                    } catch (BadRequestHttpException $e) {
                        if (!$lenient) {
                            throw $e;
                        }
                    }

                    continue;
                }

                try {
                    $esFieldInfo = $this->attributeSearch->getESFieldInfo($field);
                } catch (\InvalidArgumentException $e) {
                    if ($lenient) {
                        continue;
                    }
                    throw new BadRequestHttpException(sprintf('Invalid sort attribute "%s". Did you forget prefixing with "@" for built-in or use "field_type_s" format', $field), $e);
                }
                if (!$esFieldInfo->enabled) {
                    continue;
                }

                $fieldName = $esFieldInfo->name;
                if ($esFieldInfo->type->getElasticSearchSortSubField()) {
                    $fieldName .= '.'.$esFieldInfo->type->getElasticSearchSortSubField();
                }

                if ($esFieldInfo->builtIn) {
                    $sort[] = [$fieldName => $w];
                } else {
                    $sort[] = [$fieldName => [
                        'order' => $w,
                        'unmapped_type' => 'keyword',
                    ]];
                }
            }
        }

        if (!isset($options['order']) || ($lenient && empty($sort))) {
            $sort[] = [
                '_score' => 'DESC',
                'createdAt' => 'DESC',
            ];
        }

        $sort[] = ['microseconds' => 'DESC'];
        $sort[] = ['sequence' => 'ASC'];

        $query->setSort($sort);
    }

    protected function getAdminScope(): ?string
    {
        return AssetVoter::SCOPE_PREFIX.AbstractVoter::LIST;
    }
}
