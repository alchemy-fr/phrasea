<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Elasticsearch\ElasticSearchClient;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Elasticsearch\Mapping\IndexMappingUpdater;
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
    )
    {
    }

    public function __invoke(AttributeEntityDelete $message): void
    {
        $id = $message->getId();

        $definitions = $this->attributeDefinitionRepository->getWorkspaceDefinitionOfEntity(
            $message->getWorkspaceId(),
            $message->getType(),
        );

        if (empty($definitions)) {
            return;
        }

        $fields = [];
        $calls = [];
        $params = [];
        foreach ($definitions as $definition) {
            $fieldName = $this->fieldNameResolver->getFieldNameFromDefinition($definition);
            $fields[sprintf('%s.%s.%s', IndexMappingUpdater::ATTRIBUTES_FIELD, IndexMappingUpdater::NO_LOCALE, $fieldName)] = true;
            $calls[] = sprintf(
                'del(ctx._source.%2$s, \'%1$s\', params[\'_id\']);',
                $fieldName,
                IndexMappingUpdater::ATTRIBUTES_FIELD
            );
        }

        $this->attributeRepository->deleteByAttributeEntity(
            $message->getId(),
            $message->getWorkspaceId(),
            $message->getType()
        );

        $this->elasticSearchClient->updateByQuery(
            'asset',
            [
                'bool' => [
                    'should' => array_map(function (string $field) use ($id): array {
                        return [
                            'term' => [
                                $field.'.id' => $id,
                            ],
                        ];
                    }, array_keys($fields)),
                ],
            ],
            [
                'source' => <<<EOF
void del(HashMap c, String name, String id) {
    if (c instanceof Map) {
        for (def entry : c.entrySet()) {
            String locale = entry.getKey();

            def field = c[locale].get(name);
            if (field instanceof List) {
                field.removeIf(item -> item['id'] == id);
            } else if (field instanceof Map) {
                c[locale].remove(name);
            }
        }
    }
}

EOF.implode("\n", $calls),
                'params' => array_merge($params, [
                    '_id' => $id,
                ]),
                'lang' => 'painless',
            ]
        );
    }
}
