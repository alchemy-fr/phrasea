<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Attribute\AttributeInterface;
use App\Elasticsearch\ElasticSearchClient;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Entity\Core\AttributeEntity;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Repository\Core\AttributeEntityRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AttributeEntityUpdateHandler
{
    public function __construct(
        private ElasticSearchClient $elasticSearchClient,
        private AttributeDefinitionRepository $attributeDefinitionRepository,
        private AttributeEntityRepository $attributeEntityRepository,
        private FieldNameResolver $fieldNameResolver,
    ) {
    }

    public function __invoke(AttributeEntityUpdate $message): void
    {
        /** @var AttributeEntity $attributeEntity */
        $id = $message->getId();
        $attributeEntity = DoctrineUtil::findStrictByRepo($this->attributeEntityRepository, $id);
        $definitions = $this->attributeDefinitionRepository->getWorkspaceDefinitionOfEntity(
            $attributeEntity->getWorkspaceId(),
            $attributeEntity->getType(),
        );

        $fields = [];
        $calls = [];
        $params = [];
        foreach ($definitions as $definition) {
            $fieldName = $this->fieldNameResolver->getFieldNameFromDefinition($definition);
            foreach ($message->getChanges() as $locale => $change) {
                $fields[sprintf('%s.%s.%s', AttributeInterface::ATTRIBUTES_FIELD, AttributeInterface::NO_LOCALE, $fieldName)] = true;
                $params[$locale] = $change;
                $calls[$locale] = sprintf(
                    'up(ctx._source.%3$s, \'%1$s\', \'%2$s\', params[\'_id\'], params[\'%1$s\'], %4$s);',
                    $locale,
                    $fieldName,
                    AttributeInterface::ATTRIBUTES_FIELD,
                    $definition->isMultiple() ? 'true' : 'false',
                );
            }
        }

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
void up(HashMap attrs, String locale, String name, String id, String n, boolean m) {
    if (attrs == null) {
        attrs = [:];
    }
    HashMap node = attrs.get(locale);
    if (!(node instanceof Map)) {
        node = attrs[locale] = [:];
    }
    def field = node.get(name);

    if (m) {
        if (!(field instanceof List)) {
            field = node[name] = [];
        }
        for (item in field) {
            if (item['id'] == id) {
                if (!n.isEmpty()) {
                    item['value'] = n;
                } else {
                    field.remove(field.indexOf(item));
                }
                return;
            }
        }

        if (!n.isEmpty()) {
            def ref = attrs['_']?.get(name);
            if (ref instanceof List) {
                for (item in ref) {
                    if (item['id'] == id) {
                        field.add(["id": id, "value": n]);
                        return;
                    }
                }
            }
        }
        return;
    }

    if (field instanceof Map) {
        if (field['id'] == id) {
            if (!n.isEmpty()) {
                field['value'] = n;
            } else {
                node.remove(name);
            }
        }
    } else if (!n.isEmpty()) {
        def ref = attrs['_']?.get(name);

        if (ref instanceof Map) {
            if (ref['id'] == id) {
                node[name] = ["id": id, "value": n];
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
