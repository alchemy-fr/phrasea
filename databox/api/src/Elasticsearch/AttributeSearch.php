<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Query;

class AttributeSearch
{
    private FieldNameResolver $fieldNameResolver;
    private EntityManagerInterface $em;
    private AttributeTypeRegistry $typeRegistry;

    public function __construct(
        FieldNameResolver $fieldNameResolver,
        EntityManagerInterface $em,
        AttributeTypeRegistry $typeRegistry
    )
    {
        $this->fieldNameResolver = $fieldNameResolver;
        $this->em = $em;
        $this->typeRegistry = $typeRegistry;
    }

    public function buildAttributeQuery(string $queryString, ?string $userId, array $groupIds, array $options = []): ?Query\AbstractQuery
    {
        $language = $options['locale'] ?? '*';

        if (null !== $userId) {
            $workspaces = $this->em
                ->getRepository(Workspace::class)
                ->getAllowedWorkspaces($userId, $groupIds, $options['workspaces'] ?? null);
        } else {
            // TODO fix this point
            $workspaces = $this->em
                ->getRepository(Workspace::class)->findAll();
        }

        if (empty($workspaces)) {
            return null;
        }

        $boolQuery = new Query\BoolQuery();
        $boolQuery->setMinimumShouldMatch(1);

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

            $multiMatch = new Query\MultiMatch();
            $multiMatch->setType(Query\MultiMatch::TYPE_BEST_FIELDS);
            $multiMatch->setQuery($queryString);
//            $multiMatch->setFuzziness(Query\MultiMatch::FUZZINESS_AUTO);
            $fields = [];
            foreach ($weights as $field => $boost) {
                $fields[] = $field.'^'.$boost;
            }
            $multiMatch->setFields($fields);

            $searchQuery->addMust($multiMatch);
            $searchQuery->addMust(new Query\Term(['workspaceId' => $workspace->getId()]));
            $boolQuery->addShould($searchQuery);
        }

        return $boolQuery;
    }
}
