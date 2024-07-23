<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\Core\AttributeDefinition;
use App\Repository\Core\AttributeDefinitionRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Aggregation;
use Elastica\Aggregation\Missing;
use Elastica\Query;

class AttributeSearch
{
    final public const OPT_STRICT_PHRASE = 'strict';
    final public const GROUP_ALL = '*';

    public function __construct(
        private readonly FieldNameResolver $fieldNameResolver,
        private readonly EntityManagerInterface $em,
        private readonly AttributeTypeRegistry $typeRegistry,
    ) {
    }

    public function buildSearchableAttributeDefinitionsGroups(?string $userId, array $groupIds): array
    {
        $definitions = $this->em->getRepository(AttributeDefinition::class)
            ->getSearchableAttributesWithPermission($userId, $groupIds);

        return $this->createClustersFromDefinitions($definitions);
    }

    public function createClustersFromDefinitions(iterable $definitions): array
    {
        $groups = [];
        foreach ($definitions as $d) {
            $fieldName = $this->fieldNameResolver->getFieldName(
                $d['slug'],
                $d['fieldType'],
                $d['multiple']
            );

            $type = $this->typeRegistry->getStrictType($d['fieldType']);

            if (null === $searchType = $type->getElasticSearchSearchType()) {
                continue;
            }

            if (null !== $subField = $type->getElasticSearchSubField()) {
                $fieldName .= '.'.$subField;
            }

            $groups[$fieldName] ??= [
                'w' => [],
                'fz' => $type->supportsElasticSearchFuzziness(),
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
            } else {
                $groups[$fieldName]['f'] = true;
            }
        }

        $clusters = [];

        foreach ($groups as $f => $group) {
            if (!$this->hasDiversity($group)) {
                $firstBoost = array_keys($group['w'])[0];

                $clusters[self::GROUP_ALL] ??= [
                    'w' => null,
                    'b' => 1,
                    'fields' => [],
                ];
                $trKey = array_keys($group['w'][$firstBoost])[0];
                $st = array_keys($group['w'][$firstBoost][$trKey])[0];
                $fieldName = sprintf('attributes.%s.%s', $trKey ? '{l}' : '_', $f);

                $clusters[self::GROUP_ALL]['fields'][$fieldName] = [
                    'st' => $st,
                    'b' => $firstBoost,
                    'fz' => $group['fz'],
                ];
            } else {
                foreach ($group['w'] as $boost => $wsB) {
                    foreach ($wsB as $tr => $wsTr) {
                        foreach ($wsTr as $st => $wsSt) {
                            sort($wsSt);
                            $uk = $st.':'.$boost.':'.$tr.':'.implode(';', $wsSt);

                            $clusters[$uk] ??= [
                                'w' => $wsSt,
                                'b' => $boost,
                                'fields' => [],
                            ];
                            $fieldName = sprintf('attributes.%s.%s', $tr ? '{l}' : '_', $f);
                            $clusters[$uk]['fields'][$fieldName] = [
                                'st' => $st,
                                'b' => $boost,
                                'fz' => $group['fz'],
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
        $clusters[self::GROUP_ALL]['fields']['title'] = [
            'st' => SearchType::Match->value,
            'b' => 1,
            'fz' => true,
        ];

        return array_values($clusters);
    }

    private function hasDiversity(array $group): bool
    {
        if ($group['f'] ?? false) {
            return true;
        }

        if (count($group['w']) > 1) {
            return true;
        }

        foreach ($group['w'] as $boost => $wsTr) {
            if (1 !== $boost || count($wsTr) > 1) {
                return true;
            }

            foreach ($wsTr as $tr => $wsSt) {
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
        array $options = []
    ): ?Query\AbstractQuery {
        $language = $options['locale'] ?? '*';

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
                $fieldName = str_replace('{l}', $language, $fieldName);

                if (SearchType::Match->value === $conf['st']) {
                    $weights[$fieldName] = $conf['b'];
                    if ($conf['fz']) {
                        $weightsFuzzy[$fieldName] = $conf['b'];
                    }
                } else {
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

            $clusterQuery->addMust($matchBoolQuery);
            if (1 !== $cluster['b']) {
                $clusterQuery->setBoost($cluster['b']);
            }

            if (null !== $cluster['w']) {
                $clusterQuery->addMust(new Query\Terms('workspaceId', $cluster['w']));
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

    public function addAttributeFilters(array $filters): Query\BoolQuery
    {
        $bool = new Query\BoolQuery();
        foreach ($filters as $filter) {
            $attr = $filter['a'];
            $xType = $filter['x'] ?? null;
            $values = $filter['v'];
            $inverted = (bool) ($filter['i'] ?? false);

            $esFieldInfo = $this->getESFieldInfo($attr);

            if ('missing' === $xType) {
                $existQuery = new Query\Exists($esFieldInfo['name']);
                if ($inverted) {
                    $bool->addMust($existQuery);
                } else {
                    $bool->addMustNot($existQuery);
                }
                continue;
            }

            if (!empty($values)) {
                $filterQuery = $esFieldInfo['type']->createFilterQuery($esFieldInfo['name'], $values);

                if ($inverted) {
                    $bool->addMustNot($filterQuery);
                } else {
                    $bool->addMust($filterQuery);
                }
            }
        }

        return $bool;
    }

    /**
     * @return array{name: string, type: AttributeTypeInterface}
     */
    public function getESFieldInfo(string $attr): array
    {
        ['field' => $field, 'type' => $type] = $this->fieldNameResolver->getFieldFromName($attr);

        return [
            'name' => $field,
            'type' => $type,
        ];
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
        array $options = []
    ): void {
        $language = $options['locale'] ?? '*';
        $position = $options['context']['position'] ?? null;

        $facetTypes = array_map(fn (AttributeTypeInterface $attributeType): string => $attributeType::getName(), array_filter($this->typeRegistry->getTypes(), fn (AttributeTypeInterface $attributeType): bool => $attributeType->supportsAggregation()));

        /** @var AttributeDefinition[] $attributeDefinitions */
        $attributeDefinitions = $this->em->getRepository(AttributeDefinition::class)
            ->getSearchableAttributes($userId, $groupIds, [
                AttributeDefinitionRepositoryInterface::OPT_FACET_ENABLED => true,
                AttributeDefinitionRepositoryInterface::OPT_TYPES => $facetTypes,
            ]);

        $facets = [];
        foreach ($attributeDefinitions as $definition) {
            $fieldName = $this->fieldNameResolver->getFieldNameFromDefinition($definition);
            $type = $this->typeRegistry->getStrictType($definition->getFieldType());
            $l = $type->isLocaleAware() && $definition->isTranslatable() ? $language : IndexMappingUpdater::NO_LOCALE;
            $field = sprintf('attributes.%s.%s', $l, $fieldName);

            if (isset($facets[$field])) {
                continue;
            }
            $facets[$field] = true;

            $meta = [
                'title' => $definition->getName(),
                'sortable' => $definition->isSortable(),
            ];
            if (TextAttributeType::getName() !== $type::getName()) {
                $meta['type'] = $type::getName();
            }
            if (ESFacetInterface::TYPE_TEXT !== $type->getFacetType()) {
                $meta['facetType'] = $type->getFacetType();
            }

            $subField = $type->getAggregationField();
            $fullFieldName = $field.($subField ? '.'.$subField : '');

            switch ($type->getFacetType()) {
                case ESFacetInterface::TYPE_TEXT:
                    $agg = new Aggregation\Terms($fieldName);
                    $agg->setField($fullFieldName);
                    $agg->setSize(20);
                    break;
                case ESFacetInterface::TYPE_BOOLEAN:
                    $agg = new Aggregation\Terms($fieldName);
                    $agg->setField($fullFieldName);
                    $agg->setSize(2);
                    break;
                case ESFacetInterface::TYPE_DATE_RANGE:
                    $agg = new Aggregation\AutoDateHistogram(
                        $fieldName,
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
                        $fieldName,
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
            $query->addAggregation($agg);

            $missingAgg = new Missing($fieldName.FacetHandler::MISSING_SUFFIX, $fullFieldName);
            $query->addAggregation($missingAgg);
        }
    }
}
