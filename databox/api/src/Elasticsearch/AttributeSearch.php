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
use App\Entity\Core\Workspace;
use App\Repository\Core\AttributeDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Aggregation;
use Elastica\Aggregation\Missing;
use Elastica\Query;
use InvalidArgumentException;

class AttributeSearch
{
    public const OPT_STRICT_PHRASE = 'strict';

    private FieldNameResolver $fieldNameResolver;
    private EntityManagerInterface $em;
    private AttributeTypeRegistry $typeRegistry;
    private AssetTitleResolver $assetTitleResolver;

    public function __construct(
        FieldNameResolver $fieldNameResolver,
        EntityManagerInterface $em,
        AttributeTypeRegistry $typeRegistry,
        AssetTitleResolver $assetTitleResolver
    ) {
        $this->fieldNameResolver = $fieldNameResolver;
        $this->em = $em;
        $this->typeRegistry = $typeRegistry;
        $this->assetTitleResolver = $assetTitleResolver;
    }

    public function buildAttributeQuery(
        string $queryString,
        ?string $userId,
        array $groupIds,
        array $options = []
    ): ?Query\AbstractQuery {
        $language = $options['locale'] ?? '*';

        $workspaces = $this->em->getRepository(Workspace::class)->findAll();

        $boolQuery = new Query\BoolQuery();
        $boolQuery->setMinimumShouldMatch(1);
        $strict = $options[self::OPT_STRICT_PHRASE] ?? false;

        foreach ($workspaces as $workspace) {
            $wsSearchQuery = new Query\BoolQuery();

            /** @var AttributeDefinition[] $attributeDefinitions */
            $attributeDefinitions = $this->em->getRepository(AttributeDefinition::class)
                ->getSearchableAttributes([$workspace->getId()], $userId, $groupIds);

            $weights = [];
            if (!$this->assetTitleResolver->hasTitleOverride($workspace->getId())) {
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
            $wsSearchQuery->addMust(new Query\Term(['workspaceId' => $workspace->getId()]));

            $boolQuery->addShould($wsSearchQuery);
        }

        return $boolQuery;
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
        $workspaces = $this->em->getRepository(Workspace::class)->getUserWorkspaces($userId, $groupIds, $options['workspaces'] ?? null);

        if (empty($workspaces)) {
            return;
        }

        $facetTypes = array_map(function (AttributeTypeInterface $attributeType): string {
            return $attributeType::getName();
        }, array_filter($this->typeRegistry->getTypes(), function (AttributeTypeInterface $attributeType): bool {
            return $attributeType->supportsAggregation();
        }));

        /** @var AttributeDefinition[] $attributeDefinitions */
        $attributeDefinitions = $this->em->getRepository(AttributeDefinition::class)
            ->getSearchableAttributes(array_map(function (Workspace $w): string {
                return $w->getId();
            }, $workspaces), $userId, $groupIds, [
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
                'sortable' => $definition->isSortable()
            ];
            if (TextAttributeType::getName() !== $type::getName()) {
                $meta['type'] = $type::getName();
            }
            if (ESFacetInterface::TYPE_STRING !== $type->getFacetType()) {
                $meta['facetType'] = $type->getFacetType();
            }

            switch ($type->getFacetType()) {
                case ESFacetInterface::TYPE_STRING:
                    $agg = new Aggregation\Terms($fieldName);
                    $subField = $type->getAggregationField();
                    $agg->setField($field.($subField ? '.'.$subField : ''));
                    $agg->setSize(20);
                    break;
                case ESFacetInterface::TYPE_BOOLEAN:
                    $agg = new Aggregation\Terms($fieldName);
                    $subField = $type->getAggregationField();
                    $agg->setField($field.($subField ? '.'.$subField : ''));
                    $agg->setSize(2);
                    break;
                case ESFacetInterface::TYPE_DATE_RANGE:
                    $subField = $type->getAggregationField();
                    $agg = new Aggregation\AutoDateHistogram(
                        $fieldName,
                        $field.($subField ? '.'.$subField : '')
                    );
                    $agg->setBuckets(20);
                    break;
                case ESFacetInterface::TYPE_GEO_DISTANCE:
                    if (!$position) {
                        continue 2;
                    }
                    $geoPoint = array_map(function (string $c): float {
                        return (float) $c;
                    }, explode(',', $position));
                    $subField = $type->getAggregationField();
                    $agg = new Aggregation\GeoDistance(
                        $fieldName,
                        $field.($subField ? '.'.$subField : ''),
                        implode(',', $geoPoint)
                    );

                    $meta['position'] = $geoPoint;
                    $distances = [
                        100,
                        500,
                        1000,
                        5000,
                        10000
                    ];
                    $ranges = [];
                    for ($i = 0; $i < count($distances) - 1; $i++) {
                        $r = ['key' => (string) $i];
                        if ($i > 0) {
                            $r['from'] = $distances[$i - 1] * 1000;
                        }
                        if ($i < count($distances) - 1) {
                            $r['to'] = $distances[$i] * 1000;
                        }
                        $ranges[] =  $r;
                    }
                    $agg->setParam('ranges', $ranges);
                    break;
                default:
                    throw new InvalidArgumentException(sprintf('Unsupported facet type "%s"', $type->getFacetType()));
            }

            $agg->setMeta($meta);
            $query->addAggregation($agg);

            $missingAgg = new Missing($fieldName.FacetHandler::MISSING_SUFFIX, $fieldName);
            $query->addAggregation($missingAgg);
        }
    }
}
