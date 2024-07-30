<?php

declare(strict_types=1);

namespace App\Elasticsearch\Mapping;

use App\Attribute\AttributeTypeRegistry;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Elastica\Index;

final readonly class IndexMappingUpdater
{
    final public const ATTRIBUTES_FIELD = 'attributes';
    final public const NO_LOCALE = '_';

    public function __construct(
        private ElasticsearchClient $client,
        private Index $index,
        private EntityManagerInterface $em,
        private AttributeTypeRegistry $attributeTypeRegistry,
        private FieldNameResolver $fieldNameResolver,
    ) {
    }

    public function assignAttributeToMapping(array &$mapping, string $locale, AttributeDefinition $definition): void
    {
        $fieldName = $this->fieldNameResolver->getFieldNameFromDefinition($definition);
        $mapping['properties'][self::ATTRIBUTES_FIELD] ??= [
            'type' => 'object',
            'properties' => [],
        ];

        $properties = &$mapping['properties'][self::ATTRIBUTES_FIELD]['properties'];

        $properties[$locale] ??= [
            'type' => 'object',
            'properties' => [],
        ];

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

        if (in_array($mapping['type'], [
            'object',
            'nested',
        ], true)) {
            unset($mapping['meta']);
        }

        return $mapping;
    }

    public function synchronizeWorkspace(Workspace $workspace): void
    {
        $mapping = $this->index->getMapping();

        $attributes = $mapping['properties'][self::ATTRIBUTES_FIELD]['properties'] ?? [];

        $newMapping = [
            'properties' => [
                self::ATTRIBUTES_FIELD => [
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
        $fieldName = $this->fieldNameResolver->getFieldNameFromDefinition($definition);
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

        $assign(self::NO_LOCALE);
        if ($type->isLocaleAware() && ($definition->isTranslatable() || $type->supportsTranslations())) {
            foreach ($workspace->getEnabledLocales() as $locale) {
                $assign($locale);
            }
        }

        return $upsert;
    }

    private function isSameMapping(array $mapping, AttributeDefinition $definition, string $locale): bool
    {
        return empty(array_diff($mapping, $this->getFieldMapping($definition, $locale)));
    }
}
