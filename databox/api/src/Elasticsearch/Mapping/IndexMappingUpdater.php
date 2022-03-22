<?php

declare(strict_types=1);

namespace App\Elasticsearch\Mapping;

use App\Attribute\AttributeTypeRegistry;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use App\Util\LocaleUtils;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Elastica\Client;
use FOS\ElasticaBundle\Elastica\Index;
use FOS\ElasticaBundle\Exception\AliasIsIndexException;
use RuntimeException;

class IndexMappingUpdater
{
    public const NO_LOCALE = '_';

    private Client $client;
    private Index $index;
    private EntityManagerInterface $em;
    private AttributeTypeRegistry $attributeTypeRegistry;
    private FieldNameResolver $fieldNameResolver;

    public function __construct(
        Client $client,
        Index $index,
        EntityManagerInterface $em,
        AttributeTypeRegistry $attributeTypeRegistry,
        FieldNameResolver $fieldNameResolver
    ) {
        $this->client = $client;
        $this->index = $index;
        $this->em = $em;
        $this->attributeTypeRegistry = $attributeTypeRegistry;
        $this->fieldNameResolver = $fieldNameResolver;
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
        $language = LocaleUtils::extractLanguageFromLocale($locale);

        return array_merge([
            'type' => $type->getElasticSearchType(),
            'meta' => [
                'attribute_id' => $definition->getId(),
                'attribute_name' => $definition->getName(),
            ]
        ], $type->getElasticSearchMapping($language, $definition));
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

        $indexName = $this->getAliasedIndex($this->index->getName());
        $this->client->request($indexName.'/_mapping',
            'PUT',
            $newMapping
        );
    }

    public function assignAttributeDefinitionToMapping(array &$newMapping, AttributeDefinition $definition, array $existingAttributes = []): bool
    {
        $upsert = false;
        $fieldName = $this->fieldNameResolver->getFieldName($definition);
        $type = $this->attributeTypeRegistry->getStrictType($definition->getFieldType());

        $workspace = $definition->getWorkspace();

        $assign = function (string $locale) use ($fieldName, $definition, &$newMapping, &$upsert): void {
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

    /**
     * Returns the name of a single index that an alias points to or throws
     * an exception if there is more than one.
     *
     * @throws AliasIsIndexException
     */
    private function getAliasedIndex(string $aliasName): ?string
    {
        $aliasesInfo = $this->client->request('_aliases', 'GET')->getData();
        $aliasedIndexes = [];

        foreach ($aliasesInfo as $indexName => $indexInfo) {
            if ($indexName === $aliasName) {
                throw new AliasIsIndexException($indexName);
            }
            if (!isset($indexInfo['aliases'])) {
                continue;
            }

            $aliases = array_keys($indexInfo['aliases']);
            if (in_array($aliasName, $aliases, true)) {
                $aliasedIndexes[] = $indexName;
            }
        }

        if (count($aliasedIndexes) > 1) {
            throw new RuntimeException(sprintf('Alias "%s" is used for multiple indexes: ["%s"]. Make sure it\'s'.'either not used or is assigned to one index only', $aliasName, implode('", "', $aliasedIndexes)));
        }

        return array_shift($aliasedIndexes);
    }
}
