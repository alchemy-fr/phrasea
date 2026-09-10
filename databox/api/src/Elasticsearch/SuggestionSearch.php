<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Alchemy\CoreBundle\Util\LocaleUtil;
use App\Api\Traits\UserLocaleTrait;
use App\Attribute\AttributeInterface;
use App\Attribute\AttributeTypeRegistry;
use App\Elasticsearch\Aggregation\MultiTerms;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use App\Repository\Core\AttributeDefinitionRepository;
use Elastica\Aggregation;
use Elastica\Collapse;
use Elastica\Multi;
use Elastica\Query;
use Elastica\Result;
use Elastica\ResultSet;
use FOS\ElasticaBundle\Elastica\Index;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Search-as-you-type suggestions: distinct attribute values first, then collection and asset names.
 *
 * Attribute values come from the "suggestions" nested field of the asset documents
 * (see AssetPostTransformListener). A nested aggregation returns each (definition, value) pair once,
 * counting only the assets the user is allowed to see (same filters as AssetSearch), only for the
 * attribute definitions the user is allowed to read and only in the locales relevant to the user
 * (see getSuggestedDefinitions()).
 */
class SuggestionSearch extends AbstractSearch
{
    use UserLocaleTrait;

    private const string SUGGEST_FIELD = 'suggestion';
    private const string SUGGEST_SUB_FIELD = 'suggest';
    private const int MAX_RESULTS = 15;
    /**
     * Leaves room for the collection and asset names in the result list.
     */
    private const int MAX_ATTRIBUTE_VALUES = 10;
    private const array HIGHLIGHT_TAGS = [
        'pre_tags' => ['[hl]'],
        'post_tags' => ['[/hl]'],
    ];

    public function __construct(
        #[Autowire(service: 'fos_elastica.index.collection')]
        private readonly Index $collectionIndex,
        #[Autowire(service: 'fos_elastica.index.asset')]
        private readonly Index $assetIndex,
        private readonly AssetSearch $assetSearch,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository,
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
        private readonly string $kernelEnv,
        private readonly TranslatorInterface $translator,
        #[Autowire(param: 'es_index_prefix')]
        private readonly ?string $indexPrefix,
    ) {
    }

    /**
     * @return array{0: Pagerfanta, 1: array, 2: float}
     *
     * @throws NoWorkspaceAllowedException
     */
    public function search(
        ?string $userId,
        array $groupIds,
        array $options = [],
    ): array {
        $queryString = trim($options['query'] ?? '')
                |> (fn (string $x): string => preg_replace('#^"(.*)$#', '$1', $x))
                |> (fn (string $x): string => preg_replace('#(.*)"$#', '$1', $x));

        $definitions = $this->getSuggestedDefinitions($userId, $groupIds);

        $namesQuery = $this->createNamesQuery($userId, $groupIds, $options, $queryString);
        $valuesQuery = !empty($definitions)
            ? $this->createAttributeValuesQuery($userId, $groupIds, $options, $queryString, $definitions)
            : null;

        $multiSearch = new Multi\Search($this->assetIndex->getClient());
        $multiSearch->addSearch($this->collectionIndex->createSearch($namesQuery)->addIndex($this->assetIndex), 'names');
        if (null !== $valuesQuery) {
            $multiSearch->addSearch($this->assetIndex->createSearch($valuesQuery), 'values');
        }

        $start = microtime(true);
        $resultSets = $multiSearch->search()->getResultSets();
        $searchTime = microtime(true) - $start;

        $items = isset($resultSets['values']) ? $this->createAttributeValueItems($resultSets['values'], $definitions) : [];
        array_push($items, ...$this->createNameItems($resultSets['names']));
        $items = array_slice($items, 0, self::MAX_RESULTS);

        $esQuery = ['names' => $namesQuery->toArray()];
        if (null !== $valuesQuery) {
            $esQuery['values'] = $valuesQuery->toArray();
        }

        return [new Pagerfanta(new ArrayAdapter($items)), $esQuery, $searchTime];
    }

    /**
     * Definitions with suggestions enabled that the user is allowed to read, with their display
     * name and the locales of the values to suggest (see AssetPostTransformListener for the
     * indexing side):
     * - entity: exactly the user's best workspace locale, so that each entity yields one label;
     * - translatable text or keyword: the best workspace locale plus the untranslated values;
     * - other definitions: the untranslated values only.
     *
     * @return array<string, array{name: string, locales: string[], locale: ?string}> indexed by definition ID
     */
    private function getSuggestedDefinitions(?string $userId, array $groupIds): array
    {
        $bestLocales = [];
        $definitions = [];
        foreach ($this->attributeDefinitionRepository->getSearchableAttributes($userId, $groupIds, [
            AttributeDefinitionRepository::OPT_SUGGEST_ENABLED => true,
        ]) as $definition) {
            $workspace = $definition->getWorkspace();
            $type = $this->attributeTypeRegistry->getStrictType($definition->getType());

            $locale = null;
            $locales = [AttributeInterface::NO_LOCALE];
            if ($type->supportsTranslations() || ($type->isLocaleAware() && $definition->isTranslatable())) {
                $locale = $bestLocales[$workspace->getId()] ??= $this->getBestLocale($workspace);
                if (null !== $locale) {
                    $locales = $type->supportsTranslations() ? [$locale] : [$locale, AttributeInterface::NO_LOCALE];
                }
            }

            $definitions[$definition->getId()] = [
                'name' => $definition->getTranslatedField(
                    AttributeDefinition::TR_FIELD_NAME,
                    $this->getPreferredLocales($workspace),
                    $definition->getName(),
                ),
                'locales' => $locales,
                'locale' => $locale,
            ];
        }

        return $definitions;
    }

    private function getBestLocale(Workspace $workspace): ?string
    {
        $locale = $this->getBestWorkspaceLocale($workspace);

        return null !== $locale ? LocaleUtil::normalizeLocale($locale) : null;
    }

    /**
     * Collection and asset names: one hit per distinct name.
     */
    private function createNamesQuery(?string $userId, array $groupIds, array $options, string $queryString): Query
    {
        $filterQuery = new Query\BoolQuery();
        if (null !== $aclBoolQuery = $this->createACLBoolQuery($userId, $groupIds)) {
            $filterQuery->addFilter($aclBoolQuery);
        }
        if (isset($options['workspaces'])) {
            $filterQuery->addFilter(new Query\Terms('workspaceId', $options['workspaces']));
        }
        $filterQuery->addMust(new Query\MatchQuery(self::SUGGEST_FIELD.'.'.self::SUGGEST_SUB_FIELD, $queryString));

        $query = new Query($filterQuery);
        $query->setTrackTotalHits(false);
        $query->setSort([
            '_score' => 'DESC',
            'createdAt' => 'DESC',
        ]);
        $query->setSize(self::MAX_RESULTS);
        $query->setHighlight(self::HIGHLIGHT_TAGS + [
            'fields' => [
                self::SUGGEST_FIELD.'.'.self::SUGGEST_SUB_FIELD => new \stdClass(),
            ],
        ]);
        $collapse = new Collapse();
        $collapse->setFieldname(self::SUGGEST_FIELD.'.raw');
        $query->setCollapse($collapse);
        $query->setSource(false);
        $query->setIndicesBoost([
            $this->assetIndex->getName() => 1.1,
        ]);

        return $query;
    }

    /**
     * Distinct attribute values matching the query: no hit, only a nested aggregation.
     *
     * @param array<string, array{name: string, locales: string[], locale: ?string}> $definitions see getSuggestedDefinitions()
     */
    private function createAttributeValuesQuery(
        ?string $userId,
        array $groupIds,
        array $options,
        string $queryString,
        array $definitions,
    ): Query {
        $suggestionsField = AttributeInterface::SUGGESTIONS_FIELD;
        $valueField = sprintf('%s.value.%s', $suggestionsField, self::SUGGEST_SUB_FIELD);

        // Applies to the nested "suggestion" documents: it selects the assets, then filters the
        // aggregated values again because an asset matched through one value of a multi-valued
        // attribute must not contribute its other values.
        $suggestionQuery = new Query\BoolQuery();
        $suggestionQuery->addFilter($this->createScopeQuery($suggestionsField, $definitions));
        $suggestionQuery->addMust(new Query\MatchQuery($valueField, $queryString));

        $nestedQuery = new Query\Nested();
        $nestedQuery->setPath($suggestionsField);
        $nestedQuery->setQuery($suggestionQuery);

        $filterQuery = new Query\BoolQuery();
        foreach ($this->assetSearch->createBaseFilterQueries($userId, $groupIds, $options) as $filter) {
            $filterQuery->addFilter($filter);
        }
        if (isset($options['workspaces'])) {
            $filterQuery->addFilter(new Query\Terms('workspaceId', $options['workspaces']));
        }
        $filterQuery->addFilter($nestedQuery);

        $query = new Query($filterQuery);
        $query->setSize(0);
        $query->setTrackTotalHits(false);

        $highlight = new Aggregation\TopHits('highlight');
        $highlight->setSize(1);
        $highlight->setSource(false);
        $highlight->setHighlight(self::HIGHLIGHT_TAGS + [
            'fields' => [
                $valueField => [
                    'highlight_query' => $suggestionQuery->toArray(),
                ],
            ],
        ]);

        $values = new MultiTerms('values', [
            $suggestionsField.'.definitionId',
            $suggestionsField.'.value',
        ]);
        $values->setSize(self::MAX_ATTRIBUTE_VALUES);
        $values->addAggregation($highlight);

        $matching = new Aggregation\Filter('matching', $suggestionQuery);
        $matching->addAggregation($values);

        $suggestions = new Aggregation\Nested($suggestionsField, $suggestionsField);
        $suggestions->addAggregation($matching);
        $query->addAggregation($suggestions);

        return $query;
    }

    /**
     * Restricts the nested documents to the allowed definitions, each in the locales to suggest.
     * Definitions sharing the same locales are grouped to keep the query short.
     *
     * @param array<string, array{name: string, locales: string[], locale: ?string}> $definitions
     */
    private function createScopeQuery(string $suggestionsField, array $definitions): Query\AbstractQuery
    {
        $groups = [];
        foreach ($definitions as $definitionId => $definition) {
            $key = implode(',', $definition['locales']);
            $groups[$key] ??= [
                'locales' => $definition['locales'],
                'definitionIds' => [],
            ];
            $groups[$key]['definitionIds'][] = $definitionId;
        }

        $queries = array_map(function (array $group) use ($suggestionsField): Query\BoolQuery {
            $query = new Query\BoolQuery();
            $query->addFilter(new Query\Terms($suggestionsField.'.definitionId', $group['definitionIds']));
            $query->addFilter(new Query\Terms($suggestionsField.'.locale', $group['locales']));

            return $query;
        }, array_values($groups));

        if (1 === count($queries)) {
            return $queries[0];
        }

        $scope = new Query\BoolQuery();
        $scope->setMinimumShouldMatch(1);
        foreach ($queries as $query) {
            $scope->addShould($query);
        }

        return $scope;
    }

    /**
     * @param array<string, array{name: string, locales: string[], locale: ?string}> $definitions see getSuggestedDefinitions()
     */
    private function createAttributeValueItems(ResultSet $resultSet, array $definitions): array
    {
        $valueField = sprintf('%s.value.%s', AttributeInterface::SUGGESTIONS_FIELD, self::SUGGEST_SUB_FIELD);
        $buckets = $resultSet->getAggregation(AttributeInterface::SUGGESTIONS_FIELD)['matching']['values']['buckets'] ?? [];

        return array_map(function (array $bucket) use ($valueField, $definitions): array {
            [$definitionId, $value] = $bucket['key'];

            $item = [
                'id' => md5($definitionId.$value),
                'name' => $value,
                'hl' => $bucket['highlight']['hits']['hits'][0]['highlight'][$valueField][0] ?? $value,
                't' => $definitionId,
                'tName' => $definitions[$definitionId]['name'],
            ];
            if (null !== $definitions[$definitionId]['locale']) {
                $item['locale'] = $definitions[$definitionId]['locale'];
            }

            return $item;
        }, $buckets);
    }

    private function createNameItems(ResultSet $resultSet): array
    {
        $highlightField = self::SUGGEST_FIELD.'.'.self::SUGGEST_SUB_FIELD;
        $indexNames = [
            'asset_'.$this->kernelEnv => 'asset',
            'collection_'.$this->kernelEnv => 'collection',
        ];

        return array_map(function (Result $result) use ($highlightField, $indexNames): array {
            $hl = $result->getHighlights()[$highlightField][0] ?? '';
            $indexName = substr((string) preg_replace('#_\d{4}-\d{2}-\d{2}-\d{6}$#', '', $result->getIndex()), strlen($this->indexPrefix ?? ''));
            $type = $indexNames[$indexName];

            return [
                'id' => $result->getId(),
                'name' => preg_replace('#\[/?hl]#', '', $hl),
                'hl' => $hl,
                't' => $type,
                'tName' => $this->translator->trans(sprintf('search.suggestion.type.%s', $type)),
                'tId' => $result->getId(),
            ];
        }, $resultSet->getResults());
    }
}
