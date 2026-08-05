<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Attribute\AttributeInterface;
use App\Elasticsearch\AQL\ConditionOperatorEnum;
use App\Elasticsearch\BuiltInAttribute\AssetStatusBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\DeletedBuiltInAttribute;
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
    public function __construct(
        #[Autowire(service: 'fos_elastica.finder.asset')]
        private readonly PaginatedFinderInterface $finder,
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
    ) {
    }

    public function search(
        ?string $userId,
        array $groupIds,
        array $options = [],
    ): array {
        $maxLimit = 50;
        $options['userId'] = $userId;
        $options['groupIds'] = $groupIds;

        if (isset($options['savedSearch'])) {
            /** @var SavedSearch $savedSearch */
            $savedSearch = DoctrineUtil::findStrictByRepo($this->savedSearchRepository, $options['savedSearch']);
            $options = array_merge($options, $savedSearch->getData());
        }

        $filterQueries = [];

        $aclBoolQuery = $this->createACLBoolQuery($userId, $groupIds);
        if (null !== $aclBoolQuery) {
            $filterQueries[] = $aclBoolQuery;
        }

        if (isset($options['parent'])) {
            $options['parents'] = [$options['parent']];
        }

        if (isset($options['story'])) {
            $filterQueries[] = new Query\Term(['stories' => $options['story']]);
        }

        if (isset($options['parents'])) {
            $parentCollections = DoctrineUtil::getFromIds($this->collectionRepository, $options['parents']);
            $paths = array_map(fn (Collection $parentCollection): string => $parentCollection->getAbsolutePath(), $parentCollections);

            if (empty($paths)) {
                throw new NotFoundHttpException('Collections not found');
            }

            $filterQueries[] = new Query\Terms('collectionPaths', $paths);
        }

        if (isset($options['ids'])) {
            $filterQueries[] = new Query\Terms('_id', $options['ids']);
            $maxLimit = 500;
        }

        if (isset($options['workspaces'])) {
            $filterQueries[] = new Query\Terms('workspaceId', $options['workspaces']);
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
                    $condition = $condition['query'] ?? '';
                }

                if (str_starts_with((string) $condition, DeletedBuiltInAttribute::getKey())) {
                    $hasDeletedFilter = true;
                }
                if (str_starts_with((string) $condition, AssetStatusBuiltInAttribute::getKey())) {
                    $hasStatusFilter = true;
                }
                $filterQueries[] = $this->attributeSearch->buildConditionQuery(
                    $this->attributeSearch->buildSearchableAttributeDefinitionsGroups($userId, $groupIds),
                    $condition,
                    $options
                );
            }
        }

        if (!$hasDeletedFilter) {
            $filterQueries[] = $this->deletedBuiltInAttribute->createFilterQuery(false, ConditionOperatorEnum::EQUALS, $options);
        }

        if (!$hasStatusFilter) {
            $filterQueries[] = $this->assetStatusBuiltInAttribute->createFilterQuery(AssetStatusEnum::Accepted, ConditionOperatorEnum::EQUALS, $options);
        }

        $filterQuery = new Query\BoolQuery();
        foreach ($filterQueries as $query) {
            $filterQuery->addFilter($query);
        }

        if (null !== $attrFilterQuery = $this->buildAttributeFilterQuery($userId, $groupIds, $options)) {
            $filterQuery->addFilter($attrFilterQuery);
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
        $result->getCurrentPageResults(); // Force query to ensure adapter will run it just once.
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

    private function buildAttributeFilterQuery(?string $userId, array $groupIds, array $options): ?Query\BoolQuery
    {
        $ruleSet = $this->attributeFilterManager->getUserRules($userId, $groupIds);

        $query = new Query\BoolQuery();
        $hasConditions = false;

        foreach ($ruleSet as $wId => $conditions) {
            foreach ($conditions as $condition) {
                try {
                    $conditionQuery = $this->attributeSearch->buildConditionQuery(
                        $this->attributeSearch->buildAllAttributeDefinitionsGroups(),
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

    private function applySort(Query $query, array $options): void
    {
        $sort = [];
        if (isset($options['order'])) {
            foreach ($options['order'] as $field => $way) {
                if (!is_string($field)) {
                    throw new BadRequestHttpException(sprintf('Invalid sort field "%s"', $field));
                }
                $w = strtoupper((string) $way);
                if (!in_array($w, ['ASC', 'DESC'], true)) {
                    throw new BadRequestHttpException(sprintf('Invalid sort way "%s"', $way));
                }

                try {
                    $esFieldInfo = $this->attributeSearch->getESFieldInfo($field);
                } catch (\InvalidArgumentException $e) {
                    throw new BadRequestHttpException(sprintf('Invalid sort attribute "%s". Did you forget prefixing with "@" for built-in or use "field_type_s" format', $field), $e);
                }
                if (!$esFieldInfo->enabled) {
                    continue;
                }

                $fieldName = $esFieldInfo->name;
                if ($esFieldInfo->type->getElasticSearchSortSubField()) {
                    $fieldName .= '.'.$esFieldInfo->type->getElasticSearchSortSubField();
                }

                $sort[] = [$fieldName => $w];
            }
        } else {
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
