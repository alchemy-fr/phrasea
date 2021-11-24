<?php

declare(strict_types=1);

namespace App\Elasticsearch\Mapping;

use App\Attribute\AttributeTypeRegistry;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Elastica\Client;
use FOS\ElasticaBundle\Elastica\Index;
use FOS\ElasticaBundle\Exception\AliasIsIndexException;

class IndexMappingUpdater
{
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
        $type = $this->attributeTypeRegistry->getStrictType($definition->getFieldType());
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

        $lProps[$fieldName] = [
            'type' => $type->getElasticSearchType(),
            'meta' => [
                'attribute_id' => $definition->getId(),
                'attribute_name' => $definition->getName(),
            ]
        ];

        if (null !== $analyzer = $type->getSearchAnalyzer($this->extractLanguageFromLocale($locale))) {
            $lProps[$fieldName]['analyzer'] = $analyzer;
        }
    }

    private function extractLanguageFromLocale(string $locale): string
    {
        return preg_replace('#_.+$#', '', $locale);
    }

    public function synchronizeWorkspace(Workspace $workspace): void
    {
        $mapping = $this->index->getMapping();

        $attributes = $mapping['properties']['attributes']['properties'];

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
            $fieldName = $definition->getSearchFieldName();

            foreach ($workspace->getEnabledLocales() as $locale) {
                $upsert = false;
                if (isset($attributes[$locale][$fieldName])) {
                    $a = $attributes[$locale][$fieldName];

                    $type = $this->attributeTypeRegistry->getStrictType($definition->getFieldType());

                    if (
                        $a['type'] !== $type->getElasticSearchType()
                        || $a['analyzer'] !== $type->getSearchAnalyzer($this->extractLanguageFromLocale($locale))
                    ) {
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
            }
        }

        $indexName = $this->getAliasedIndex($this->index->getName());
        $this->client->request($indexName.'/_mapping',
            'PUT',
            $newMapping
        );
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
            throw new \RuntimeException(sprintf('Alias "%s" is used for multiple indexes: ["%s"]. Make sure it\'s'.'either not used or is assigned to one index only', $aliasName, implode('", "', $aliasedIndexes)));
        }

        return array_shift($aliasedIndexes);
    }
}
