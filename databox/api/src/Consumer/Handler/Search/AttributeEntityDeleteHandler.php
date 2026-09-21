<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Attribute\AttributeInterface;
use App\Elasticsearch\ElasticSearchClient;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Repository\Core\AttributeRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AttributeEntityDeleteHandler
{
    public function __construct(
        private ElasticSearchClient $elasticSearchClient,
        private AttributeDefinitionRepository $attributeDefinitionRepository,
        private AttributeRepository $attributeRepository,
        private FieldNameResolver $fieldNameResolver,
    ) {
    }

    public function __invoke(AttributeEntityDelete $message): void
    {
        $id = $message->getId();

        $definitions = $this->attributeDefinitionRepository->getWorkspaceDefinitionOfEntity(
            $message->getWorkspaceId(),
            $message->getListId(),
        );

        if (empty($definitions)) {
            return;
        }

        $fields = [];
        $calls = [
            AttributeEntitySuggestionsScript::CALL,
        ];
        $params = [
            '_entityIds' => [$id],
            // No label left: the suggestions of the entity are removed
            '_labels' => AttributeEntitySuggestionsScript::labels([]),
        ];
        foreach ($definitions as $definition) {
            $fieldName = $this->fieldNameResolver->getFieldNameFromDefinition($definition);
            $fields[sprintf('%s.%s.%s', AttributeInterface::ATTRIBUTES_FIELD, AttributeInterface::NO_LOCALE, $fieldName)] = true;
            $calls[] = sprintf(
                'del(ctx._source.%2$s, \'%1$s\', params[\'_id\']);',
                $fieldName,
                AttributeInterface::ATTRIBUTES_FIELD
            );
        }

        $this->attributeRepository->deleteByAttributeEntity(
            $message->getId(),
            $message->getWorkspaceId(),
            $message->getListId()
        );

        $this->elasticSearchClient->updateByQuery(
            'asset',
            [
                'bool' => [
                    'should' => array_map(fn (string $field): array => [
                        'term' => [
                            $field.'.id' => $id,
                        ],
                    ], array_keys($fields)),
                ],
            ],
            [
                // "attrs" is null for an asset without any attribute (see AssetPostTransformListener)
                // and a locale node may be missing: guard every dereference, a painless NPE
                // fails the whole update_by_query with a bare "runtime error".
                'source' => <<<EOF
void del(def attrs, String name, String id) {
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
        if (field instanceof List) {
            field.removeIf(item -> item instanceof Map && item['id'] == id);
        } else if (field instanceof Map && field.id == id) {
            node.remove(name);
        }
    }
}

EOF.AttributeEntitySuggestionsScript::declaration().implode("\n", $calls),
                'params' => array_merge($params, [
                    '_id' => $id,
                ]),
                'lang' => 'painless',
            ]
        );
    }
}
