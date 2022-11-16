<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Asset\Attribute\AssetTitleResolver;
use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\DateAttributeType;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use App\Repository\Core\AttributeDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Aggregation;
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
            $searchQuery = new Query\BoolQuery();

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
                    || $type instanceof DateAttributeType
                )) {
                    continue;
                }

                $l = $type->isLocaleAware() && $definition->isTranslatable() ? $language : IndexMappingUpdater::NO_LOCALE;

                $field = sprintf('attributes.%s.%s', $l, $fieldName);
                if ($type instanceof DateAttributeType) {
                    $field .= '.text';
                }
                $weights[$field] = $definition->getSearchBoost() ?? 1;
            }

            $multiMatch = $this->createMultiMatch($queryString, $weights, false, $options);
            if ($strict) {
                $searchQuery->addMust($multiMatch);
            } else {
                $subBool = new Query\BoolQuery();
                $multiMatch->setParam('boost', 50);
                $subBool->addShould($multiMatch);
                $subBool->addShould($this->createMultiMatch($queryString, $weights, true, $options));
                $searchQuery->addMust($subBool);
            }

            $searchQuery->addMust(new Query\Term(['workspaceId' => $workspace->getId()]));
            $boolQuery->addShould($searchQuery);
        }

        return $boolQuery;
    }

    public function addAttributeFilters(array $filters): Query\BoolQuery
    {
        $bool = new Query\BoolQuery();
        foreach ($filters as $filter) {
            $attr = $filter['a'];
            $values = $filter['v'];
            $inverted = (bool) ($filter['i'] ?? false);

            $esFieldInfo = $this->getESFieldInfo($attr);

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
        $type = TextAttributeType::getName();
        if (FacetHandler::FACET_WORKSPACE === $attr) {
            $f = 'workspaceId';
        } elseif (FacetHandler::FACET_COLLECTION === $attr) {
            $f = 'collectionPaths';
        } elseif (FacetHandler::FACET_TAG === $attr) {
            $f = 'tags';
        } elseif (FacetHandler::FACET_PRIVACY === $attr) {
            $f = 'privacy';
        } elseif (FacetHandler::FACET_CREATED_AT === $attr) {
            $type = DateAttributeType::getName();
            $f = 'createdAt';
        } else {
            $info = $this->fieldNameResolver->extractField($attr);
            $t = $this->typeRegistry->getStrictType($info['type']);
            $type = $t::getName();
            $f = sprintf('attributes._.%s', $info['field']);
            if (null !== $subField = $t->getAggregationField()) {
                $f .= '.'.$subField;
            }
        }

        return [
            'name' => $f,
            'type' => $this->typeRegistry->getStrictType($type),
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

            switch ($type->getFacetType()) {
                case FacetInterface::TYPE_STRING:
                    $agg = new Aggregation\Terms($fieldName);
                    $subField = $type->getAggregationField();
                    $agg->setField($field.($subField ? '.'.$subField : ''));
                    $agg->setSize(5);
                    break;
                case FacetInterface::TYPE_DATE_RANGE:
                    $subField = $type->getAggregationField();
                    $agg = new Aggregation\AutoDateHistogram(
                        $fieldName,
                        $field.($subField ? '.'.$subField : '')
                    );
                    $agg->setBuckets(20);
                    break;
                default:
                    throw new InvalidArgumentException(sprintf('Unsupported facet type "%s"', $type->getFacetType()));
            }


            $meta = [
                'title' => $definition->getName(),
            ];

            $type = $this->typeRegistry->getStrictType($definition->getFieldType());
            if ($type->getFacetType() !== FacetInterface::TYPE_STRING) {
                $meta['type'] = $type->getFacetType();
            }

            $agg->setMeta($meta);

            $query->addAggregation($agg);
        }
    }
}
