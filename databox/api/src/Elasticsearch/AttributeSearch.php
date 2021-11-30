<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use App\Util\LocaleUtils;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Query;

class AttributeSearch
{
    private FieldNameResolver $fieldNameResolver;
    private EntityManagerInterface $em;

    public function __construct(FieldNameResolver $fieldNameResolver, EntityManagerInterface $em)
    {
        $this->fieldNameResolver = $fieldNameResolver;
        $this->em = $em;
    }

    public function buildAttributeQuery(string $queryString, ?string $userId, array $groupIds, array $options = []): ?Query\AbstractQuery
    {
        $language = isset($options['lang']) ? LocaleUtils::extractLanguageFromLocale($options['lang']) : '*';

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
                ->getSearchableAttributes([$workspace->getId()]);

            $weights = [
                'title' => 10,
            ];

            foreach ($attributeDefinitions as $definition) {
                $fieldName = $this->fieldNameResolver->getFieldName($definition);
                $weights[sprintf('attributes.%s.%s', $language, $fieldName)] = $definition->getSearchBoost() ?? 1;
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
