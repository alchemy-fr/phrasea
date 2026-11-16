<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Api\Traits\UserLocaleTrait;
use App\Attribute\AttributeInterface;
use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\AQL\AQLParser;
use App\Elasticsearch\AQL\AQLToESQuery;
use App\Elasticsearch\Mapping\FieldInfoDto;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Entity\Core\AttributeDefinition;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Service\Asset\AssetPolicy\AssetPolicyManager;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Aggregation;
use Elastica\Aggregation\Missing;
use Elastica\Query;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Cache\CacheInterface;

class AttributeSearch
{
    use UserLocaleTrait;

    final public const string OPT_STRICT_PHRASE = 'strict';
    final public const string GROUP_ALL = '*';
    private const string LOCALE_WILDCARD = '*';

    private CacheInterface $cache;

    public function __construct(
        private readonly FieldNameResolver $fieldNameResolver,
        private readonly EntityManagerInterface $em,
        private readonly AttributeTypeRegistry $typeRegistry,
        private readonly AQLParser $AQLParser,
        private readonly AQLToESQuery $AQLToESQuery,
        private readonly AssetPolicyManager $assetPolicyManager,
        TemporaryCacheFactory $cacheFactory,
    ) {
        $this->cache = $cacheFactory->createCache();
    }

    public function buildSearchableAttributeDefinitionsGroups(?string $userId, array $groupIds): array
    {
        return $this->cache->get('sa_'.$userId, function () use ($userId, $groupIds) {
            $definitions = [];
            foreach ($this->getDefinitionsWithPermission($userId, $groupIds) as $d) {
                // An unreadable definition, even a non-searchable one, writes into the ES field
                // of the searchable definitions sharing its key: they must not match its values.
                if ($d['searchable'] || !$d['allowed']) {
                    $definitions[] = $d;
                }
            }

            return $this->createClustersFromDefinitions($definitions);
        });
    }

    public function buildAllAttributeDefinitionsGroups(): array
    {
        return $this->cache->get('sa_all', function () {
            $definitions = $this->em->getRepository(AttributeDefinition::class)
                ->getAllSearchableAttributes();

            return $this->createClustersFromDefinitions($definitions);
        });
    }

    /**
     * Definitions sharing a key share the same ES field: the key is sortable as soon as one of them is.
     *
     * @return bool|null null when no definition produces this key
     */
    public function isSortableAttributeKey(string $key): ?bool
    {
        $index = $this->cache->get('sortable_keys', function (): array {
            $index = [];
            foreach ($this->em->getRepository(AttributeDefinition::class)->getSortabilityRows() as $row) {
                $fieldName = $this->fieldNameResolver->getFieldName($row['slug'], $row['type'], $row['multiple']);
                $index[$fieldName] = ($index[$fieldName] ?? false) || $row['sortable'];
            }

            return $index;
        });

        return $index[$key] ?? null;
    }

    /**
     * Whether the user may read the values of at least one definition producing this key.
     */
    public function isReadableAttributeKey(?string $userId, array $groupIds, string $key): bool
    {
        return [] !== $this->getReadableWorkspaceIds($userId, $groupIds, $key);
    }

    /**
     * The workspaces in which the indexed values of an attribute key may be used for the user
     * (matched, aggregated, sorted). The ES field of a key holds the values of the definitions
     * of every workspace, whereas the user may only read some of them.
     *
     * @return string[]|null null when the user may read every definition producing the key,
     *                       an empty list when they may read none
     */
    public function getReadableWorkspaceIds(?string $userId, array $groupIds, string $key): ?array
    {
        $index = $this->getKeyVisibilityIndex($userId, $groupIds);

        return array_key_exists($key, $index) ? $index[$key]['w'] : [];
    }

    /**
     * Within the readable workspaces of an attribute key, the assets on which asset policies
     * hide its definition from the user (see HiddenAttributeScope).
     *
     * @return list<array{w: string, p: string[]}>
     */
    public function getHiddenScopes(?string $userId, array $groupIds, string $key): array
    {
        return $this->getKeyVisibilityIndex($userId, $groupIds)[$key]['x'] ?? [];
    }

    /**
     * @return array<string, array{w: string[]|null, x: list<array{w: string, p: string[]}>}>
     */
    private function getKeyVisibilityIndex(?string $userId, array $groupIds): array
    {
        return $this->cache->get('rk_'.$userId, function () use ($userId, $groupIds): array {
            $readable = [];
            $restricted = [];
            $hidden = [];
            foreach ($this->getDefinitionsWithPermission($userId, $groupIds) as $row) {
                $k = $this->fieldNameResolver->getFieldName($row['slug'], $row['type'], $row['multiple']);
                $readable[$k] ??= [];
                if ($row['allowed']) {
                    $readable[$k][(string) $row['workspaceId']] = true;
                    foreach ($row['hidden'] ?? [] as $paths) {
                        $hidden[$k][] = ['w' => (string) $row['workspaceId'], 'p' => $paths];
                    }
                } else {
                    $restricted[$k] = true;
                }
            }

            $index = [];
            foreach ($readable as $k => $workspaceIds) {
                $index[$k] = [
                    'w' => isset($restricted[$k]) ? array_map(strval(...), array_keys($workspaceIds)) : null,
                    'x' => $hidden[$k] ?? [],
                ];
            }

            return $index;
        });
    }

    /**
     * Restricts the documents whose values of an attribute key may be used to the workspaces
     * where the user may read it, minus the assets on which asset policies hide it.
     *
     * @return Query\AbstractQuery|null null when the values may be used wherever they are indexed
     */
    public function createVisibilityQuery(?string $userId, array $groupIds, string $key): ?Query\AbstractQuery
    {
        $workspaceIds = $this->getReadableWorkspaceIds($userId, $groupIds, $key);
        if ([] === $workspaceIds) {
            return new Query\MatchNone();
        }

        $workspaceQuery = null !== $workspaceIds ? new Query\Terms('workspaceId', $workspaceIds) : null;
        $exclusionQuery = HiddenAttributeScope::createExclusionQuery($this->getHiddenScopes($userId, $groupIds, $key));
        if (null === $exclusionQuery) {
            return $workspaceQuery;
        }
        if (null !== $workspaceQuery) {
            $exclusionQuery->addFilter($workspaceQuery);
        }

        return $exclusionQuery;
    }

    /**
     * Every definition with whether the user may read its values ("allowed"): a definition that
     * an asset policy hides from every asset of its workspace is not, and "hidden" lists, for
     * each other policy hiding it, the collection paths the policy applies to (see
     * AssetPolicyManager::getHiddenAttributeScopes()).
     *
     * @return array<array<string, mixed>>
     */
    private function getDefinitionsWithPermission(?string $userId, array $groupIds): array
    {
        return $this->cache->get('dp_'.$userId, function () use ($userId, $groupIds): array {
            $hiddenScopes = $this->assetPolicyManager->getHiddenAttributeScopes($userId, $groupIds);

            $rows = [];
            foreach ($this->em->getRepository(AttributeDefinition::class)->getAttributesWithPermission($userId, $groupIds) as $row) {
                $workspaceId = (string) $row['workspaceId'];
                $scopes = HiddenAttributeScope::ofDefinition($hiddenScopes, (string) ($row['id'] ?? ''), $workspaceId);
                if ($row['allowed'] && !empty($scopes)) {
                    if (HiddenAttributeScope::coversWorkspace($scopes, $workspaceId)) {
                        $row['allowed'] = false;
                    } else {
                        $row['hidden'] = array_column($scopes, 'p');
                    }
                }
                $rows[] = $row;
            }

            return $rows;
        });
    }

    public function createClustersFromDefinitions(iterable $definitions): array
    {
        $groups = [];
        $localesIndex = [];
        $locales = [];
        foreach ($definitions as $d) {
            foreach ($d['enabledLocales'] as $locale) {
                $localesIndex[(string) $d['workspaceId']][$locale] = true;
                $locales[$locale] = true;
            }
            $fieldName = $this->fieldNameResolver->getFieldName(
                $d['slug'],
                $d['type'],
                $d['multiple']
            );

            $type = $this->typeRegistry->getStrictType($d['type']);

            $searchType = $type->getElasticSearchSearchType() ?? SearchType::Other;

            $groups[$fieldName] ??= [
                'w' => [],
                'type' => $type,
            ];

            $boost = $d['searchBoost'] ?? 1;
            $trIndex = $type->isLocaleAware() && ($d['translatable'] || $type->supportsTranslations()) ? 1 : 0;

            if ($d['allowed']) {
                $groups[$fieldName]['w'][$boost] ??= [
                    $trIndex => [],
                ];
                $groups[$fieldName]['w'][$boost][$trIndex] ??= [
                    $searchType->value => [],
                ];
                $groups[$fieldName]['w'][$boost][$trIndex][$searchType->value][] = $d['workspaceId'];
                if (!empty($d['hidden'])) {
                    $groups[$fieldName]['x'][(string) $d['workspaceId']] = $d['hidden'];
                }
            } else {
                $groups[$fieldName]['f'] = true;
            }
        }

        $clusters = [];

        foreach ($groups as $f => $group) {
            $type = $group['type'];
            if (!$this->hasDiversity($group)) {
                $firstBoost = array_keys($group['w'])[0];

                $clusters[self::GROUP_ALL] ??= [
                    'w' => null,
                    'b' => 1,
                    'fields' => [],
                ];
                $trKey = array_keys($group['w'][$firstBoost])[0];
                $fieldName = sprintf('%s.%s.%s', AttributeInterface::ATTRIBUTES_FIELD, $trKey ? '{l}' : '_', $f);

                $clusters[self::GROUP_ALL]['fields'][$fieldName] = [
                    'b' => $firstBoost,
                    'type' => $type,
                ];
            } else {
                foreach ($group['w'] as $boost => $wsB) {
                    foreach ($wsB as $tr => $wsTr) {
                        foreach ($wsTr as $st => $wsSt) {
                            sort($wsSt);
                            $uk = $st.':'.$boost.':'.$tr.':'.implode(';', $wsSt);

                            // A field that asset policies hide on some assets only shares its
                            // cluster with the fields hidden on the same assets.
                            $hidden = [];
                            foreach ($wsSt as $workspaceId) {
                                foreach ($group['x'][(string) $workspaceId] ?? [] as $paths) {
                                    $hidden[] = ['w' => (string) $workspaceId, 'p' => $paths];
                                }
                            }
                            if (!empty($hidden)) {
                                $uk .= ':'.json_encode($hidden, JSON_THROW_ON_ERROR);
                            }

                            $clusters[$uk] ??= [
                                'w' => $wsSt,
                                'b' => $boost,
                                'fields' => [],
                                'locales' => array_merge(...array_map(function (string $workspaceId) use ($localesIndex): array {
                                    if (isset($localesIndex[$workspaceId])) {
                                        return array_keys($localesIndex[$workspaceId]);
                                    }

                                    return [];
                                }, $wsSt)),
                            ];
                            if (!empty($hidden)) {
                                $clusters[$uk]['x'] = $hidden;
                            }
                            $fieldName = sprintf('%s.%s.%s', AttributeInterface::ATTRIBUTES_FIELD, $tr ? '{l}' : '_', $f);
                            $clusters[$uk]['fields'][$fieldName] = [
                                'b' => $boost,
                                'type' => $type,
                            ];
                        }
                    }
                }
            }
        }

        $clusters[self::GROUP_ALL] ??= [
            'w' => null,
            'b' => 1,
            'fields' => [],
        ];
        $clusters[self::GROUP_ALL]['locales'] = array_keys($locales);
        $clusters[self::GROUP_ALL]['fields']['name'] = [
            'b' => 1,
            'type' => $this->typeRegistry->getStrictType(TextAttributeType::NAME),
        ];

        return array_values($clusters);
    }

    private function hasDiversity(array $group): bool
    {
        if (($group['f'] ?? false) || !empty($group['x'])) {
            return true;
        }

        if (count($group['w']) > 1) {
            return true;
        }

        foreach ($group['w'] as $boost => $wsTr) {
            if (1 !== $boost || count($wsTr) > 1) {
                return true;
            }

            foreach ($wsTr as $wsSt) {
                if (count($wsSt) > 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param AttributeDefinition[] $attributeDefinitionClusters
     */
    public function buildAttributeQuery(
        array $attributeDefinitionClusters,
        string $queryString,
        array $options = [],
    ): ?Query\AbstractQuery {
        $language = $options['locale'] ?? self::LOCALE_WILDCARD;

        $boolQuery = new Query\BoolQuery();
        $boolQuery->setMinimumShouldMatch(1);
        $strict = $options[self::OPT_STRICT_PHRASE] ?? false;

        $this->addIdQueryShould($boolQuery, $queryString);

        foreach ($attributeDefinitionClusters as $cluster) {
            $clusterQuery = new Query\BoolQuery();
            $matchBoolQuery = new Query\BoolQuery();
            $weights = [];
            $weightsFuzzy = [];

            foreach ($cluster['fields'] as $fieldName => $conf) {
                /** @var AttributeTypeInterface $type */
                $type = $conf['type'];
                $fieldName = $fieldNameBase = str_replace('{l}', $language, $fieldName);
                if ($type->getElasticSearchTextSubField()) {
                    $fieldName .= '.'.$type->getElasticSearchTextSubField();
                }

                $searchType = $type->getElasticSearchSearchType();
                if (SearchType::Match === $searchType) {
                    $weights[$fieldName] = $conf['b'];
                    if ($type->supportsElasticSearchFuzziness()) {
                        $weightsFuzzy[$fieldName] = $conf['b'];
                    }

                    foreach ($type->getAdditionalSubFields($conf['b']) as $subField => $boost) {
                        $f = $fieldNameBase.'.'.$subField;
                        $weights[$f] = $boost;
                        if ($type->supportsElasticSearchFuzziness()) {
                            $weightsFuzzy[$f] = $boost;
                        }
                    }
                } elseif (SearchType::Keyword === $searchType) {
                    $term = new Query\Term([$fieldName => $queryString]);
                    if (1 !== $conf['b']) {
                        $term->setParam('boost', $conf['b']);
                    }
                    $matchBoolQuery->addShould($term);
                }
            }

            if (!empty($weights)) {
                $multiMatch = $this->createMultiMatch($queryString, $weights, false, $options);
                $matchBoolQuery->addShould($multiMatch);

                if (!$strict && !empty($weightsFuzzy)) {
                    $multiMatch->setParam('boost', 5);
                    $matchBoolQuery->addShould($this->createMultiMatch($queryString, $weightsFuzzy, true, $options));
                }
            }

            if (!$matchBoolQuery->hasParam('should') || empty($matchBoolQuery->getParam('should'))) {
                // If no match query was created, we can skip this cluster
                continue;
            }

            $clusterQuery->addMust($matchBoolQuery);
            if (1 !== $cluster['b']) {
                $clusterQuery->setBoost($cluster['b']);
            }

            if (!empty($cluster['w'])) {
                $clusterQuery->addMust(new Query\Terms('workspaceId', $cluster['w']));
            }
            foreach ($cluster['x'] ?? [] as $scope) {
                $clusterQuery->addMustNot(HiddenAttributeScope::createQuery($scope));
            }

            $boolQuery->addShould($clusterQuery);
        }

        return $boolQuery;
    }

    protected function addIdQueryShould(Query\BoolQuery $parentQuery, string $queryString): void
    {
        $queryString = trim($queryString);

        if (1 === preg_match('#([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})#', $queryString, $uuid)) {
            $parentQuery->addShould(new Query\Term(['_id' => $uuid[1]]));
        }
    }

    public function getESFieldInfo(string $attr): FieldInfoDto
    {
        return $this->fieldNameResolver->getFieldFromName($attr);
    }

    private function createMultiMatch(string $queryString, array $weights, bool $fuzziness, array $options): Query\MultiMatch
    {
        $multiMatch = new Query\MultiMatch();
        $multiMatch->setType(Query\MultiMatch::TYPE_BEST_FIELDS);
        $multiMatch->setQuery($queryString);

        if ($options[self::OPT_STRICT_PHRASE] ?? false) {
            $multiMatch->setOperator(Query\MultiMatch::OPERATOR_AND);
        }

        if ($fuzziness) {
            $multiMatch->setFuzziness(Query\MultiMatch::FUZZINESS_AUTO.':5,8');
        }

        $fields = [];
        foreach ($weights as $field => $boost) {
            $fields[] = $field.'^'.$boost;
        }
        $multiMatch->setFields($fields);
        $multiMatch->setParam('lenient', true);

        return $multiMatch;
    }

    public function buildFacets(
        Query $query,
        ?string $userId,
        array $groupIds,
        array $options = [],
    ): void {
        $position = $options['context']['position'] ?? null;

        $attributeTypes = array_map(fn (AttributeTypeInterface $attributeType): string => $attributeType::getName(), array_filter($this->typeRegistry->getTypes(), fn (AttributeTypeInterface $attributeType): bool => $attributeType->supportsAggregation()));

        /** @var AttributeDefinition[] $attributeDefinitions */
        $attributeDefinitions = $this->em->getRepository(AttributeDefinition::class)
            ->getSearchableAttributes($userId, $groupIds, [
                AttributeDefinitionRepository::OPT_FACET_ENABLED => true,
                AttributeDefinitionRepository::OPT_TYPES => $attributeTypes,
            ]);

        $facets = [];
        foreach ($attributeDefinitions as $definition) {
            $fieldName = $this->fieldNameResolver->getFieldNameFromDefinition($definition);
            $type = $this->typeRegistry->getStrictType($definition->getType());

            $l = $type->isLocaleAware() && $definition->isTranslatable() ?
                ($this->getBestWorkspaceLocale($definition->getWorkspace()) ?? AttributeInterface::NO_LOCALE)
                : AttributeInterface::NO_LOCALE;

            $field = sprintf('%s.%s.%s', AttributeInterface::ATTRIBUTES_FIELD, $l, $fieldName);

            if (isset($facets[$field])) {
                continue;
            }
            $facets[$field] = true;

            $displayName = $definition->getTranslatedField(AttributeDefinition::TR_FIELD_NAME, $this->getPreferredLocales($definition->getWorkspace()), $definition->getName());

            $meta = [
                'displayName' => $displayName,
                'sortable' => $definition->isSortable(),
            ];
            if (AttributeInterface::NO_LOCALE !== $l) {
                $meta['locale'] = $l;
            }
            if (TextAttributeType::getName() !== $type::getName()) {
                $meta['type'] = $type::getName();
            }
            if (ESFacetInterface::TYPE_TEXT !== $type->getFacetType()) {
                $meta['facetType'] = $type->getFacetType();
            }

            $subField = $type->getAggregationField();
            $fullFieldName = $field.($subField ? '.'.$subField : '');

            // Values of the workspaces where the user cannot read the key must not be counted:
            // the aggregations are then nested in a filter aggregation, unwrapped by FacetHandler.
            $visibility = $this->createVisibilityQuery($userId, $groupIds, $fieldName);
            $aggName = null !== $visibility ? FacetHandler::VISIBLE_AGG : $fieldName;

            switch ($type->getFacetType()) {
                case ESFacetInterface::TYPE_TEXT:
                case ESFacetInterface::TYPE_ENTITY:
                    $agg = new Aggregation\Terms($aggName);
                    $agg->setField($fullFieldName);
                    $agg->setSize(20);
                    break;
                case ESFacetInterface::TYPE_BOOLEAN:
                    $agg = new Aggregation\Terms($aggName);
                    $agg->setField($fullFieldName);
                    $agg->setSize(2);
                    break;
                case ESFacetInterface::TYPE_DATE_RANGE:
                    $agg = new Aggregation\AutoDateHistogram(
                        $aggName,
                        $fullFieldName
                    );
                    $agg->setBuckets(20);
                    break;
                case ESFacetInterface::TYPE_GEO_DISTANCE:
                    if (!$position) {
                        continue 2;
                    }
                    $geoPoint = array_map(fn (string $c): float => (float) $c, explode(',', (string) $position));
                    $agg = new Aggregation\GeoDistance(
                        $aggName,
                        $fullFieldName,
                        implode(',', $geoPoint)
                    );

                    $meta['position'] = $geoPoint;
                    $distances = [
                        100,
                        500,
                        1000,
                        5000,
                        10000,
                    ];
                    $ranges = [];
                    for ($i = 0; $i < count($distances) - 1; ++$i) {
                        $r = ['key' => (string) $i];
                        if ($i > 0) {
                            $r['from'] = $distances[$i - 1] * 1000;
                        }
                        if ($i < count($distances) - 1) {
                            $r['to'] = $distances[$i] * 1000;
                        }
                        $ranges[] = $r;
                    }
                    $agg->setParam('ranges', $ranges);
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf('Unsupported facet type "%s"', $type->getFacetType()));
            }

            $agg->setMeta($meta);
            $missingAgg = new Missing(null !== $visibility ? FacetHandler::VISIBLE_AGG : $fieldName.FacetHandler::MISSING_SUFFIX, $fullFieldName);

            if (null !== $visibility) {
                $agg = (new Aggregation\Filter($fieldName, $visibility))->addAggregation($agg);
                $missingAgg = (new Aggregation\Filter($fieldName.FacetHandler::MISSING_SUFFIX, $visibility))->addAggregation($missingAgg);
            }

            $query->addAggregation($agg);
            $query->addAggregation($missingAgg);
        }
    }

    public function buildConditionQuery(
        array $fieldClusters,
        string $condition,
        array $options,
    ): Query\AbstractQuery {
        $ast = $this->AQLParser->parse($condition);
        if (null === $ast) {
            throw new BadRequestHttpException(sprintf('Invalid condition: %s', $condition));
        }

        return $this->AQLToESQuery->createQuery($fieldClusters, $ast['data'], $options);
    }
}
