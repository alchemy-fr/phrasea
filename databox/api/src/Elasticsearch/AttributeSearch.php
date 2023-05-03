<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Asset\Attribute\AssetTitleResolver;
use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\DateTimeAttributeType;
use App\Attribute\Type\KeywordAttributeType;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\Core\AttributeDefinition;
use App\Repository\Core\AttributeDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Aggregation;
use Elastica\Aggregation\Missing;
use Elastica\Query;
use InvalidArgumentException;

class AttributeSearch
{
    final public const OPT_STRICT_PHRASE = 'strict';

    public function __construct(private readonly FieldNameResolver $fieldNameResolver, private readonly EntityManagerInterface $em, private readonly AttributeTypeRegistry $typeRegistry, private readonly AssetTitleResolver $assetTitleResolver)
    {
    }

    public function buildAttributeQuery(
        string $queryString,
        ?string $userId,
        array $groupIds,
        array $options = []
    ): ?Query\AbstractQuery {
        $language = $options['locale'] ?? '*';

        $boolQuery = new Query\BoolQuery();
        $boolQuery->setMinimumShouldMatch(1);
        $strict = $options[self::OPT_STRICT_PHRASE] ?? false;

        $this->addIdQueryShould($boolQuery, $queryString);

        /** @var AttributeDefinition[] $attributeDefinitions */
        $attributeDefinitions = $this->em->getRepository(AttributeDefinition::class)
            ->getSearchableAttributes($userId, $groupIds);

        $wsIndexed = [];
        foreach ($attributeDefinitions as $definition) {
            $workspaceId = $definition->getWorkspaceId();
            $wsIndexed[$workspaceId] ??= [];
            $wsIndexed[$workspaceId][] = $definition;
        }

        foreach ($wsIndexed as $workspaceId => $attributeDefinitions) {
            $wsSearchQuery = new Query\BoolQuery();

            $weights = [];
            if (!$this->assetTitleResolver->hasTitleOverride($workspaceId)) {
                $weights['title'] = 10;
            }

            foreach ($attributeDefinitions as $definition) {
                $fieldName = $this->fieldNameResolver->getFieldName($definition);
                $type = $this->typeRegistry->getStrictType($definition->getFieldType());

                if (!(
                    $type instanceof TextAttributeType
                    || $type instanceof DateTimeAttributeType
                )) {
                    continue;
                }

                $l = $type->isLocaleAware() && $definition->isTranslatable() ? $language : IndexMappingUpdater::NO_LOCALE;

                $field = sprintf('attributes.%s.%s', $l, $fieldName);
                if ($type instanceof DateTimeAttributeType) {
                    $field .= '.text';
                }
                $weights[$field] = $definition->getSearchBoost() ?? 1;
            }

            $matchBoolQuery = new Query\BoolQuery();

            $multiMatch = $this->createMultiMatch($queryString, $weights, false, $options);
            $multiMatch->setParam('boost', 50);
            $matchBoolQuery->addShould($multiMatch);

            if (!$strict) {
                $matchBoolQuery->addShould($this->createMultiMatch($queryString, $weights, true, $options));
            }

            // Add should for terms
            foreach ($attributeDefinitions as $definition) {
                $fieldName = $this->fieldNameResolver->getFieldName($definition);
                $type = $this->typeRegistry->getStrictType($definition->getFieldType());

                if ($type instanceof KeywordAttributeType) {
                    $l = $type->isLocaleAware() && $definition->isTranslatable() ? $language : IndexMappingUpdater::NO_LOCALE;
                    $field = sprintf('attributes.%s.%s', $l, $fieldName);
                    $matchBoolQuery->addShould(new Query\Term([$field => $queryString]));
                }
            }

            $wsSearchQuery->addMust($matchBoolQuery);
            $wsSearchQuery->addMust(new Query\Term(['workspaceId' => $workspaceId]));

            $boolQuery->addShould($wsSearchQuery);
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

        $facetTypes = array_map(fn(AttributeTypeInterface $attributeType): string => $attributeType::getName(), array_filter($this->typeRegistry->getTypes(), fn(AttributeTypeInterface $attributeType): bool => $attributeType->supportsAggregation()));

        /** @var AttributeDefinition[] $attributeDefinitions */
        $attributeDefinitions = $this->em->getRepository(AttributeDefinition::class)
            ->getSearchableAttributes($userId, $groupIds, [
                AttributeDefinitionRepository::OPT_FACET_ENABLED => true,
                AttributeDefinitionRepository::OPT_TYPES => $facetTypes,
            ]);

        $facets = [];
        foreach ($attributeDefinitions as $definition) {
            $fieldName = $this->fieldNameResolver->getFieldName($definition);
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
                    $geoPoint = array_map(fn(string $c): float => (float) $c, explode(',', (string) $position));
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
                    throw new InvalidArgumentException(sprintf('Unsupported facet type "%s"', $type->getFacetType()));
            }

            $agg->setMeta($meta);
            $query->addAggregation($agg);

            $missingAgg = new Missing($fieldName.FacetHandler::MISSING_SUFFIX, $fullFieldName);
            $query->addAggregation($missingAgg);
        }
    }
}
