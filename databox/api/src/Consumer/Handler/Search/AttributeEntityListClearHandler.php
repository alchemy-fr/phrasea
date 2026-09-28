<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Attribute\AttributeInterface;
use App\Elasticsearch\ElasticSearchClient;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Entity\Core\AttributeDefinition;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Repository\Core\AttributeEntityRepository;
use App\Repository\Core\AttributeRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AttributeEntityListClearHandler
{
    public function __construct(
        private ElasticSearchClient $elasticSearchClient,
        private AttributeDefinitionRepository $attributeDefinitionRepository,
        private AttributeRepository $attributeRepository,
        private AttributeEntityRepository $attributeEntityRepository,
        private FieldNameResolver $fieldNameResolver,
    ) {
    }

    public function __invoke(AttributeEntityListClear $message): void
    {
        $definitions = $this->attributeDefinitionRepository->getWorkspaceDefinitionOfEntity(
            $message->getWorkspaceId(),
            $message->getListId(),
        );

        if (empty($definitions)) {
            return;
        }

        $fields = [];
        $calls = [];
        foreach ($definitions as $definition) {
            $fieldName = $this->fieldNameResolver->getFieldNameFromDefinition($definition);
            $fields[sprintf('%s.%s.%s', AttributeInterface::ATTRIBUTES_FIELD, AttributeInterface::NO_LOCALE, $fieldName)] = true;
            $calls[] = sprintf(
                'del(ctx._source.%2$s, \'%1$s\');',
                $fieldName,
                AttributeInterface::ATTRIBUTES_FIELD
            );
        }
        // The entities are gone: so are their suggestions
        $calls[] = AttributeEntitySuggestionsScript::removeDefinitionsCall();

        $this->attributeEntityRepository->createQueryBuilder('t')
            ->delete()
            ->where('t.list = :listId')
            ->andWhere('t.workspace = :workspaceId')
            ->setParameter('listId', $message->getListId())
            ->setParameter('workspaceId', $message->getWorkspaceId())
            ->getQuery()
            ->execute();

        $this->elasticSearchClient->deleteByQuery('attributeEntity', [
            'bool' => [
                'must' => [
                    [
                        'term' => [
                            'listId' => $message->getListId(),
                        ],
                    ],
                    [
                        'term' => [
                            'workspaceId' => $message->getWorkspaceId(),
                        ],
                    ],
                ],
            ],
        ]);

        $this->attributeRepository->deleteByAttributeEntityList(
            $message->getListId(),
            $message->getWorkspaceId(),
        );

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
                    '_definitionIds' => array_map(fn (AttributeDefinition $definition): string => $definition->getId(), $definitions),
                ],
                'lang' => 'painless',
            ]
        );
    }
}
