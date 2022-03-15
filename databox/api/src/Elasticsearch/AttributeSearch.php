<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\KeywordAttributeType;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use App\Repository\Core\AttributeDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Aggregation;
use Elastica\Query;

class AttributeSearch
{
    public const OPT_STRICT_PHRASE = 'strict';

    private FieldNameResolver $fieldNameResolver;
    private EntityManagerInterface $em;
    private AttributeTypeRegistry $typeRegistry;

    public function __construct(
        FieldNameResolver $fieldNameResolver,
        EntityManagerInterface $em,
        AttributeTypeRegistry $typeRegistry
    ) {
        $this->fieldNameResolver = $fieldNameResolver;
        $this->em = $em;
        $this->typeRegistry = $typeRegistry;
    }

    public function buildAttributeQuery(
        string $queryString,
        ?string $userId,
        array $groupIds,
        array $options = []
    ): ?Query\AbstractQuery {
        $language = $options['locale'] ?? '*';

        $workspaces = $this->em->getRepository(Workspace::class)->getUserWorkspaces($userId, $groupIds, $options['workspaces'] ?? null);

        if (empty($workspaces)) {
            return null;
        }

        $boolQuery = new Query\BoolQuery();
        $boolQuery->setMinimumShouldMatch(1);

        $strict = $options[self::OPT_STRICT_PHRASE] ?? false;

        foreach ($workspaces as $workspace) {
            $searchQuery = new Query\BoolQuery();

            /** @var AttributeDefinition[] $attributeDefinitions */
            $attributeDefinitions = $this->em->getRepository(AttributeDefinition::class)
                ->getSearchableAttributes([$workspace->getId()], $userId, $groupIds);

            $weights = [
                'title' => 10,
            ];

            foreach ($attributeDefinitions as $definition) {
                $fieldName = $this->fieldNameResolver->getFieldName($definition);
                $type = $this->typeRegistry->getStrictType($definition->getFieldType());

                $l = $type->isLocaleAware() && $definition->isTranslatable() ? $language : IndexMappingUpdater::NO_LOCALE;

                $field = sprintf('attributes.%s.%s', $l, $fieldName);
                if (!$type instanceof TextAttributeType) {
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

    private function createMultiMatch(string $queryString, array $weights, bool $fuzziness, array $options): Query\MultiMatch
    {
        $multiMatch = new Query\MultiMatch();
        $multiMatch->setType(Query\MultiMatch::TYPE_BEST_FIELDS);
        $multiMatch->setQuery($queryString);

        if ($options[self::OPT_STRICT_PHRASE] ?? false) {
            $multiMatch->setOperator(Query\MultiMatch::OPERATOR_AND);
        }

        if ($fuzziness) {
            $multiMatch->setFuzziness(Query\MultiMatch::FUZZINESS_AUTO);
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
        Query\BoolQuery $boolQuery,
        ?string $userId,
        array $groupIds,
        array $options = []
    ): void {
        $language = $options['locale'] ?? '*';
        $workspaces = $this->em->getRepository(Workspace::class)->getUserWorkspaces($userId, $groupIds, $options['workspaces'] ?? null);

        if (empty($workspaces)) {
            return;
        }

        /** @var AttributeDefinition[] $attributeDefinitions */
        $attributeDefinitions = $this->em->getRepository(AttributeDefinition::class)
            ->getSearchableAttributes(array_map(function (Workspace $w): string {
                return $w->getId();
            }, $workspaces), $userId, $groupIds, [
                AttributeDefinitionRepository::OPT_TYPE => KeywordAttributeType::getName(),
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

            $agg = new Aggregation\Terms($fieldName);
            $agg->setField($field);
            $agg->setSize(5);
            $agg->setMeta([
                'title' => $definition->getName()
            ]);
//            $agg->setOrder('_count', 'desc');

            $query->addAggregation($agg);
        }
    }
}
