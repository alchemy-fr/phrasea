<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\CoreBundle\Util\LocaleUtil;
use App\Api\Traits\UserLocaleTrait;
use App\Attribute\AttributeInterface;
use App\Attribute\AttributeTypeRegistry;
use App\Elasticsearch\Aggregation\MultiTerms;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Repository\Core\CollectionRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\AssetPolicy\AssetPolicyManager;
use Elastica\Aggregation;
use Elastica\Collapse;
use Elastica\Multi;
use Elastica\Query;
use Elastica\Result;
use Elastica\ResultSet;
use FOS\ElasticaBundle\Elastica\Index;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Search-as-you-type suggestions: distinct attribute values first, then collection names.
 *
 * Attribute values come from the "suggestions" nested field of the asset documents
 * (see AssetPostTransformListener). A nested aggregation returns each (definition, value) pair once,
 * counting only the assets the user is allowed to see (same filters as AssetSearch), only for the
 * attribute definitions the user is allowed to read, except on the assets where an asset policy
 * hides them from the user, and only in the locales relevant to the user (see getSuggestedDefinitions()).
 *
 * Scoped to attribute definitions (the "definition" option, comma-separated IDs), only their values
 * are suggested, whatever their "suggest" flag, and an empty query lists them: the search bar uses
 * it to offer the values of a field being filtered (the same attribute across workspaces).
 */
class SuggestionSearch extends AbstractSearch
{
    use UserLocaleTrait;

    private const string SUGGEST_FIELD = 'suggestion';
    private const string SUGGEST_SUB_FIELD = 'suggest';
    private const int MAX_RESULTS = 15;
    /**
     * Leaves room for the collection names in the result list.
     */
    private const int MAX_ATTRIBUTE_VALUES = 10;
    private const int MAX_SCOPED_VALUES = 15;
    private const string VALUES_AGG_PREFIX = 'values_';
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
        private readonly CollectionRepository $collectionRepository,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        private readonly AssetPolicyManager $assetPolicyManager,
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

        // One or several (comma-separated) definition IDs: the same attribute across workspaces
        $definitionIds = array_values(array_filter(array_map('trim', explode(',', (string) ($options['definition'] ?? '')))));
        if (!empty($definitionIds)) {
            // Not a UUID: no such definition (and no SQL error on the uuid column)
            $definitionIds = array_filter($definitionIds, Uuid::isValid(...));
            if (empty($definitionIds)) {
                return [new Pagerfanta(new ArrayAdapter([])), [], 0.0];
            }

            return $this->searchDefinitionValues($userId, $groupIds, $options, $queryString, $definitionIds);
        }

        $definitions = $this->getSuggestedDefinitions($userId, $groupIds);

        $namesQuery = $this->createNamesQuery($userId, $groupIds, $options, $queryString);
        $valuesQuery = !empty($definitions)
            ? $this->createAttributeValuesQuery($userId, $groupIds, $options, $queryString, $definitions)
            : null;

        $multiSearch = new Multi\Search($this->assetIndex->getClient());
        $multiSearch->addSearch($this->collectionIndex->createSearch($namesQuery), 'names');
        if (null !== $valuesQuery) {
            $multiSearch->addSearch($this->assetIndex->createSearch($valuesQuery), 'values');
        }

        $start = microtime(true);
        $resultSets = $this->executeSearch(fn (): array => $multiSearch->search()->getResultSets());
        $searchTime = microtime(true) - $start;

        // A multi search does not throw when one of its searches fails: the failed one comes back
        // as a result set holding the error, without the aggregations.
        if (isset($resultSets['values']) && $resultSets['values']->getResponse()->hasError()) {
            $this->logger->error(sprintf('Suggestion search of attribute values failed: %s', $resultSets['values']->getResponse()->getErrorMessage()));
            unset($resultSets['values']);
        }

        $items = isset($resultSets['values']) ? $this->createAttributeValueItems($resultSets['values'], $definitions, self::MAX_ATTRIBUTE_VALUES) : [];
        array_push($items, ...$this->createNameItems($resultSets['names']));
        $items = array_slice($items, 0, self::MAX_RESULTS);

        $esQuery = ['names' => $namesQuery->toArray()];
        if (null !== $valuesQuery) {
            $esQuery['values'] = $valuesQuery->toArray();
        }

        return [new Pagerfanta(new ArrayAdapter($items)), $esQuery, $searchTime];
    }

    /**
     * Values of the given attribute definitions only, whatever their "suggest" flag (the
     * permissions and the "searchable" flag still apply). An empty query lists the values.
     *
     * @param string[] $definitionIds
     *
     * @return array{0: Pagerfanta, 1: array, 2: float}
     */
    private function searchDefinitionValues(
        ?string $userId,
        array $groupIds,
        array $options,
        string $queryString,
        array $definitionIds,
    ): array {
        $definitions = $this->getSuggestedDefinitions($userId, $groupIds, $definitionIds);
        if (empty($definitions)) {
            return [new Pagerfanta(new ArrayAdapter([])), [], 0.0];
        }

        $valuesQuery = $this->createAttributeValuesQuery(
            $userId,
            $groupIds,
            $options,
            $queryString,
            $definitions,
            self::MAX_SCOPED_VALUES,
        );

        $start = microtime(true);
        $resultSet = $this->executeSearch(fn (): ResultSet => $this->assetIndex->search($valuesQuery));
        $searchTime = microtime(true) - $start;

        $items = $this->createAttributeValueItems($resultSet, $definitions, self::MAX_SCOPED_VALUES);

        return [new Pagerfanta(new ArrayAdapter($items)), ['values' => $valuesQuery->toArray()], $searchTime];
    }

    /**
     * Definitions with suggestions enabled that the user is allowed to read (or the given
     * definitions only, whatever their "suggest" flag), with their display name and the locales of
     * the values to suggest (see AssetPostTransformListener for the indexing side):
     * - entity: exactly the user's best workspace locale, so that each entity yields one label;
     * - translatable text or keyword: the best workspace locale plus the untranslated values;
     * - other definitions: the untranslated values only.
     *
     * "hidden" holds the assets of the workspace on which asset policies hide the definition from
     * the user (see HiddenAttributeScope); a definition hidden on every asset is left out.
     *
     * @return array<string, array{name: string, locales: string[], locale: ?string, hidden: list<array{w: string, p: string[]}>}> indexed by definition ID
     */
    private function getSuggestedDefinitions(?string $userId, array $groupIds, ?array $definitionIds = null): array
    {
        $repositoryOptions = null !== $definitionIds
            ? [AttributeDefinitionRepository::OPT_IDS => $definitionIds]
            : [AttributeDefinitionRepository::OPT_SUGGEST_ENABLED => true];

        $hiddenScopes = $this->assetPolicyManager->getHiddenAttributeScopes($userId, $groupIds);
        $bestLocales = [];
        $definitions = [];
        foreach ($this->attributeDefinitionRepository->getSearchableAttributes($userId, $groupIds, $repositoryOptions) as $definition) {
            $workspace = $definition->getWorkspace();
            $type = $this->attributeTypeRegistry->getStrictType($definition->getType());
            if (!$type->supportsSuggest()) {
                continue;
            }

            $hidden = HiddenAttributeScope::ofDefinition($hiddenScopes, $definition->getId(), $workspace->getId());
            if (HiddenAttributeScope::coversWorkspace($hidden, $workspace->getId())) {
                continue;
            }

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
                'hidden' => $hidden,
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
     * Collection names: one hit per distinct name.
     *
     * Assets have no name of their own (their title is an attribute, suggested as an attribute
     * value), so the asset index is not searched.
     */
    private function createNamesQuery(?string $userId, array $groupIds, array $options, string $queryString): Query
    {
        $filterQuery = new Query\BoolQuery();
        if (null !== $aclBoolQuery = $this->createACLBoolQuery($userId, $groupIds)) {
            $filterQuery->addFilter($aclBoolQuery);
        }
        if (!empty($workspaceIds = self::toIdList($options['workspaces'] ?? null))) {
            $filterQuery->addFilter(new Query\Terms('workspaceId', $workspaceIds));
        }
        $filterQuery->addFilter(new Query\Term(['deleted' => false]));
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

        return $query;
    }

    /**
     * Distinct attribute values matching the query (all of them when the query is empty): no hit,
     * only nested aggregations.
     *
     * @param array<string, array{name: string, locales: string[], locale: ?string, hidden: list<array{w: string, p: string[]}>}> $definitions see getSuggestedDefinitions()
     */
    private function createAttributeValuesQuery(
        ?string $userId,
        array $groupIds,
        array $options,
        string $queryString,
        array $definitions,
        int $size = self::MAX_ATTRIBUTE_VALUES,
    ): Query {
        $suggestionsField = AttributeInterface::SUGGESTIONS_FIELD;
        $valueField = sprintf('%s.value.%s', $suggestionsField, self::SUGGEST_SUB_FIELD);

        // Applies to the nested "suggestion" documents: it selects the assets, then each group
        // filters the aggregated values again because an asset matched through one value of a
        // multi-valued attribute must not contribute its other values.
        $createSuggestionQuery = function (array $definitions) use ($suggestionsField, $valueField, $queryString): Query\BoolQuery {
            $suggestionQuery = new Query\BoolQuery();
            $suggestionQuery->addFilter($this->createScopeQuery($suggestionsField, $definitions));
            $suggestionQuery->addMust('' !== $queryString
                ? new Query\MatchQuery($valueField, $queryString)
                : new Query\MatchAll()
            );

            return $suggestionQuery;
        };
        $suggestionQuery = $createSuggestionQuery($definitions);

        $nestedQuery = new Query\Nested();
        $nestedQuery->setPath($suggestionsField);
        $nestedQuery->setQuery($suggestionQuery);

        $filterQuery = new Query\BoolQuery();
        foreach ($this->assetSearch->createBaseFilterQueries($userId, $groupIds, $options) as $filter) {
            $filterQuery->addFilter($filter);
        }
        if (!empty($workspaceIds = self::toIdList($options['workspaces'] ?? null))) {
            $filterQuery->addFilter(new Query\Terms('workspaceId', $workspaceIds));
        }
        $filterQuery->addFilter($nestedQuery);

        $query = new Query($filterQuery);
        $query->setSize(0);
        $query->setTrackTotalHits(false);

        // One aggregation per group of definitions hidden on the same assets: a nested aggregation
        // cannot filter on the asset, a filter aggregation wraps it (see createAttributeValueItems()).
        $groups = HiddenAttributeScope::groupDefinitions(
            array_map(strval(...), array_keys($definitions)),
            array_map(static fn (array $definition): array => $definition['hidden'], $definitions),
        );
        foreach ($groups as $i => $group) {
            $groupSuggestionQuery = $createSuggestionQuery(array_intersect_key($definitions, array_flip($group['definitionIds'])));

            // Entities are keyed by their ID too: two entities may share a label, and the client
            // filters by ID. Plain values have no entity ID and get an empty one.
            $values = new MultiTerms('values', [
                $suggestionsField.'.definitionId',
                $suggestionsField.'.value',
                ['field' => $suggestionsField.'.entityId', 'missing' => ''],
            ]);
            $values->setSize($size);

            if ('' !== $queryString) {
                $highlight = new Aggregation\TopHits('highlight');
                $highlight->setSize(1);
                $highlight->setSource(false);
                $highlight->setHighlight(self::HIGHLIGHT_TAGS + [
                    'fields' => [
                        $valueField => [
                            'highlight_query' => $groupSuggestionQuery->toArray(),
                        ],
                    ],
                ]);
                $values->addAggregation($highlight);
            }

            $matching = new Aggregation\Filter('matching', $groupSuggestionQuery);
            $matching->addAggregation($values);

            $suggestions = new Aggregation\Nested($suggestionsField, $suggestionsField);
            $suggestions->addAggregation($matching);

            $visible = new Aggregation\Filter(self::VALUES_AGG_PREFIX.$i, HiddenAttributeScope::createExclusionQuery($group['scopes']) ?? new Query\MatchAll());
            $visible->addAggregation($suggestions);
            $query->addAggregation($visible);
        }

        return $query;
    }

    /**
     * Restricts the nested documents to the allowed definitions, each in the locales to suggest.
     * Definitions sharing the same locales are grouped to keep the query short.
     *
     * @param array<string, array{name: string, locales: string[], locale: ?string, hidden: list<array{w: string, p: string[]}>}> $definitions
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
     * The most frequent values of every group of definitions (see createAttributeValuesQuery()).
     *
     * @param array<string, array{name: string, locales: string[], locale: ?string, hidden: list<array{w: string, p: string[]}>}> $definitions see getSuggestedDefinitions()
     */
    private function createAttributeValueItems(ResultSet $resultSet, array $definitions, int $size): array
    {
        $valueField = sprintf('%s.value.%s', AttributeInterface::SUGGESTIONS_FIELD, self::SUGGEST_SUB_FIELD);
        $buckets = [];
        foreach ($resultSet->getAggregations() as $name => $aggregation) {
            if (str_starts_with((string) $name, self::VALUES_AGG_PREFIX)) {
                array_push($buckets, ...$aggregation[AttributeInterface::SUGGESTIONS_FIELD]['matching']['values']['buckets'] ?? []);
            }
        }
        usort($buckets, static fn (array $a, array $b): int => $b['doc_count'] <=> $a['doc_count']);
        $buckets = array_slice($buckets, 0, $size);

        return array_map(function (array $bucket) use ($valueField, $definitions): array {
            [$definitionId, $value, $entityId] = $bucket['key'];

            $item = [
                'id' => md5($definitionId.$value.$entityId),
                'name' => $value,
                'hl' => $bucket['highlight']['hits']['hits'][0]['highlight'][$valueField][0] ?? $value,
                't' => $definitionId,
                'tName' => $definitions[$definitionId]['name'],
            ];
            if (null !== $definitions[$definitionId]['locale']) {
                $item['locale'] = $definitions[$definitionId]['locale'];
            }
            if ('' !== $entityId) {
                $item['entityId'] = $entityId;
            }

            return $item;
        }, $buckets);
    }

    /**
     * The hits whose collection the user may read: a stale document (collection moved, trashed
     * or purged since its indexing) must not leak its name.
     */
    private function createNameItems(ResultSet $resultSet): array
    {
        $highlightField = self::SUGGEST_FIELD.'.'.self::SUGGEST_SUB_FIELD;
        $results = $resultSet->getResults();
        $collections = DoctrineUtil::getIndexFromIds(
            $this->collectionRepository,
            array_map(static fn (Result $result): string => (string) $result->getId(), $results),
        );
        $typeName = $this->translator->trans('search.suggestion.type.collection');

        $items = [];
        foreach ($results as $result) {
            $collection = $collections[$result->getId()] ?? null;
            if (!$collection instanceof Collection || !$this->isGranted(AbstractVoter::READ, $collection)) {
                continue;
            }

            $hl = $result->getHighlights()[$highlightField][0] ?? '';
            $items[] = [
                'id' => $result->getId(),
                'name' => preg_replace('#\[/?hl]#', '', $hl),
                'hl' => $hl,
                't' => 'collection',
                'tName' => $typeName,
                'tId' => $result->getId(),
            ];
        }

        return $items;
    }
}
