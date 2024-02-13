<?php

declare(strict_types=1);

namespace App\Elasticsearch\Mapping;

use App\Attribute\AttributeTypeRegistry;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Elastica\Index;

class IndexMappingUpdater
{
    final public const NO_LOCALE = '_';

    public function __construct(
        private readonly ElasticsearchClient $client,
        private readonly Index $index,
        private readonly EntityManagerInterface $em,
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
        private readonly FieldNameResolver $fieldNameResolver,
    ) {
    }

    public function assignAttributeToMapping(array &$mapping, string $locale, AttributeDefinition $definition): void
    {
        $fieldName = $this->fieldNameResolver->getFieldName($definition);
        if (!isset($mapping['properties']['attributes'])) {
            $mapping['properties']['attributes'] = [
                'type' => 'object',
                'properties' => [],
            ];
        }

        $properties = &$mapping['properties']['attributes']['properties'];

        if (!isset($properties[$locale])) {
            $properties[$locale] = [
                'type' => 'object',
                'properties' => [],
            ];
        }

        $lProps = &$properties[$locale]['properties'];

        $lProps[$fieldName] = $this->getFieldMapping($definition, $locale);
    }

    private function getFieldMapping(AttributeDefinition $definition, string $locale): array
    {
        $type = $this->attributeTypeRegistry->getStrictType($definition->getFieldType());

        $mapping = array_merge([
            'type' => $type->getElasticSearchType(),
            'meta' => [
                'attribute_id' => $definition->getId(),
                'attribute_name' => $definition->getName(),
            ],
        ], $type->getElasticSearchMapping($locale, $definition));

        if ($type->supportsSuggest()) {
            $mapping['fields'] ??= [];
            $mapping['fields']['suggest'] = [
                'type' => 'search_as_you_type',
                'analyzer' => 'text',
            ];
        }

        return $mapping;
    }

    public function synchronizeWorkspace(Workspace $workspace): void
    {
        $mapping = $this->index->getMapping();

        $attributes = $mapping['properties']['attributes']['properties'] ?? [];

        $newMapping = [
            'properties' => [
                'attributes' => [
                    'type' => 'object',
                    'properties' => [],
                ],
            ],
        ];

        /** @var AttributeDefinition[] $attributeDefinitions */
        $attributeDefinitions = $this->em->getRepository(AttributeDefinition::class)
            ->findBy([
                'workspace' => $workspace->getId(),
            ]);

        foreach ($attributeDefinitions as $definition) {
            $this->assignAttributeDefinitionToMapping($newMapping, $definition, $attributes);
        }

        $indexName = $this->client->getAliasedIndex($this->index->getName());

        $this->client->updateMapping($indexName, $newMapping);
    }

    public function assignAttributeDefinitionToMapping(array &$newMapping, AttributeDefinition $definition, array $existingAttributes = []): bool
    {
        $upsert = false;
        $fieldName = $this->fieldNameResolver->getFieldName($definition);
        $type = $this->attributeTypeRegistry->getStrictType($definition->getFieldType());

        $workspace = $definition->getWorkspace();

        $assign = function (string $locale) use ($existingAttributes, $fieldName, $definition, &$newMapping, &$upsert): void {
            if (isset($existingAttributes[$locale][$fieldName])) {
                $a = $existingAttributes[$locale][$fieldName];

                if (!$this->isSameMapping($a, $definition, $locale)) {
                    $upsert = true;
                }
            } else {
                $upsert = true;
            }

            if ($upsert) {
                $this->assignAttributeToMapping(
                    $newMapping,
                    $locale,
                    $definition
                );
            }
        };

        if ($type->isLocaleAware() && $definition->isTranslatable()) {
            foreach ($workspace->getEnabledLocales() as $locale) {
                $assign($locale);
            }
        } else {
            $assign(self::NO_LOCALE);
        }

        return $upsert;
    }

    private function isSameMapping(array $mapping, AttributeDefinition $definition, string $locale): bool
    {
        return empty(array_diff($mapping, $this->getFieldMapping($definition, $locale)));
    }
}
