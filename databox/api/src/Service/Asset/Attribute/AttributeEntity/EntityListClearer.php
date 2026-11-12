<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute\AttributeEntity;

use App\Attribute\AttributeInterface;
use App\Consumer\Handler\Search\AttributeEntitySuggestionsScript;
use App\Elasticsearch\ElasticSearchClient;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\ImageSet;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Repository\Core\AttributeEntityRepository;
use App\Repository\Core\AttributeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Removes every entity of a list in bulk, with the attribute values referencing them, whatever their number.
 *
 * The rows are deleted in DQL (deleteRows()), the indexes cleaned afterwards (cleanIndexes()), from the field names of
 * the definitions bound to the list, read before (getIndexedDefinitions()): deleting the list deletes these definitions.
 */
final readonly class EntityListClearer
{
    public function __construct(
        private ElasticSearchClient $elasticSearchClient,
        private AttributeDefinitionRepository $attributeDefinitionRepository,
        private AttributeRepository $attributeRepository,
        private AttributeEntityRepository $attributeEntityRepository,
        private FieldNameResolver $fieldNameResolver,
        private EntityManagerInterface $em,
    ) {
    }

    public function clear(string $listId, string $workspaceId): void
    {
        $definitions = $this->getIndexedDefinitions($listId, $workspaceId);
        $this->deleteRows($listId, $workspaceId);
        $this->cleanIndexes($listId, $workspaceId, $definitions);
    }

    /**
     * @return array<string, string> definition ID => indexed field name, of the entity definitions bound to the list
     */
    public function getIndexedDefinitions(string $listId, string $workspaceId): array
    {
        $definitions = [];
        /** @var AttributeDefinition $definition */
        foreach ($this->attributeDefinitionRepository->getWorkspaceDefinitionOfEntity($workspaceId, $listId) as $definition) {
            $definitions[$definition->getId()] = $this->fieldNameResolver->getFieldNameFromDefinition($definition);
        }

        return $definitions;
    }

    /**
     * Deletes the entities of the list, their images and the attribute values referencing them.
     */
    public function deleteRows(string $listId, string $workspaceId): void
    {
        // The values are deleted in DQL: their images are removed through the ORM afterwards,
        // so that FileListener schedules the deletion of their files.
        $imageSetIds = array_column($this->attributeEntityRepository->createQueryBuilder('t')
            ->select('IDENTITY(t.image) AS id')
            ->where('t.list = :listId')
            ->andWhere('t.workspace = :workspaceId')
            ->andWhere('t.image IS NOT NULL')
            ->setParameter('listId', $listId)
            ->setParameter('workspaceId', $workspaceId)
            ->getQuery()
            ->getScalarResult(), 'id');

        $this->attributeEntityRepository->createQueryBuilder('t')
            ->delete()
            ->where('t.list = :listId')
            ->andWhere('t.workspace = :workspaceId')
            ->setParameter('listId', $listId)
            ->setParameter('workspaceId', $workspaceId)
            ->getQuery()
            ->execute();

        $this->removeImageSets($imageSetIds);

        $this->attributeRepository->deleteByAttributeEntityList($listId, $workspaceId);
    }

    /**
     * Removes the entities of the list from the attribute entity index, and the values of the given definitions from
     * the assets.
     *
     * @param array<string, string> $definitions definition ID => indexed field name (see getIndexedDefinitions())
     */
    public function cleanIndexes(string $listId, string $workspaceId, array $definitions): void
    {
        $this->elasticSearchClient->deleteByQuery('attributeEntity', [
            'bool' => [
                'must' => [
                    [
                        'term' => [
                            'listId' => $listId,
                        ],
                    ],
                    [
                        'term' => [
                            'workspaceId' => $workspaceId,
                        ],
                    ],
                ],
            ],
        ]);

        if (empty($definitions)) {
            return;
        }

        $fields = [];
        $calls = [];
        foreach ($definitions as $fieldName) {
            $fields[sprintf('%s.%s.%s', AttributeInterface::ATTRIBUTES_FIELD, AttributeInterface::NO_LOCALE, $fieldName)] = true;
            $calls[] = sprintf(
                'del(ctx._source.%2$s, \'%1$s\');',
                $fieldName,
                AttributeInterface::ATTRIBUTES_FIELD
            );
        }
        // The entities are gone: so are their suggestions
        $calls[] = AttributeEntitySuggestionsScript::removeDefinitionsCall();

        $this->elasticSearchClient->updateByQuery(
            'asset',
            [
                'bool' => [
                    'should' => array_map(fn (string $field): array => [
                        'exists' => [
                            'field' => $field,
                        ],
                    ], array_keys($fields)),
                ],
            ],
            [
                // Same guards as AttributeEntityDeleteHandler: "attrs" may be null or a locale
                // node may be missing, which would make painless fail with a "runtime error".
                'source' => <<<EOF
void del(def attrs, String name) {
    if (!(attrs instanceof List) || attrs.isEmpty()) {
        return;
    }
    def c = attrs[0];
    if (!(c instanceof Map)) {
        return;
    }
    for (def entry : c.entrySet()) {
        def node = entry.getValue();
        if (!(node instanceof Map)) {
            continue;
        }
        def field = node.get(name);
        if (field instanceof List || field instanceof Map) {
            node.remove(name);
        }
    }
}

EOF.implode("\n", $calls),
                'params' => [
                    '_definitionIds' => array_map('strval', array_keys($definitions)),
                ],
                'lang' => 'painless',
            ]
        );
    }

    /**
     * @param string[] $ids
     */
    private function removeImageSets(array $ids): void
    {
        foreach (array_chunk($ids, 100) as $chunk) {
            foreach ($this->em->getRepository(ImageSet::class)->findBy(['id' => $chunk]) as $imageSet) {
                $this->em->remove($imageSet);
            }
            $this->em->flush();
        }
    }
}
