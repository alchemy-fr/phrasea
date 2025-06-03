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
        $workspaceId = $attributeEntity->getWorkspaceId();
        $definitions = $this->attributeDefinitionRepository->getWorkspaceDefinitionOfEntity(
            $attributeEntity->getWorkspaceId(),
            $attributeEntity->getList()->getId(),
        );

        $fields = [];
        $calls = [];
        $params = [];
        $locales = $message->getLocales();
        foreach ($definitions as $definition) {
            $fieldName = $this->fieldNameResolver->getFieldNameFromDefinition($definition);

            foreach ($locales as $locale) {
                $fields[sprintf('%s.%s.%s', AttributeInterface::ATTRIBUTES_FIELD, AttributeInterface::NO_LOCALE, $fieldName)] = true;
                $params[$locale] = [
                    'v' => $attributeEntity->getTranslations()[$locale] ?? (AttributeInterface::NO_LOCALE === $locale ? $attributeEntity->getValue() : ''),
                    's' => $attributeEntity->getSynonymsOfLocale($locale) ?? [],
                ];
                $calls[$locale] = sprintf(
                    'up(ctx._source, \'%1$s\', \'%2$s\', params[\'_id\'], params[\'%1$s\'][\'v\'], params[\'%1$s\'][\'s\'], %3$s);',
                    $locale,
                    $fieldName,
                    $definition->isMultiple() ? 'true' : 'false',
                );
            }
        }

        if (empty($fields)) {
            return;
        }

        $this->elasticSearchClient->updateByQuery(
            'asset',
            [
                'bool' => [
                    'must' => [
                        [
                            'term' => [
                                'workspaceId' => $workspaceId,
                            ],
                        ],
                    ],
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
                'source' => sprintf(<<<EOF
void up(HashMap src, String locale, String name, String id, String n, def s, boolean m) {
    HashMap attributes;

    if (src.%1\$s instanceof List) {
        attributes = src.%1\$s[0];
    } else {
        if (src.%1\$s == null) {
            src.%1\$s = [[:]];
            attributes = src.%1\$s[0];
        } else {
            attributes = src.%1\$s;
        }
    }

    boolean hasValue = !n.isEmpty() || s.size() > 0;

    HashMap node = attributes.get(locale);
    if (!(node instanceof Map)) {
        node = attributes[locale] = [:];
    }
    def field = node.get(name);

    if (m) {
        if (!(field instanceof List)) {
            field = node[name] = [];
        }
        for (item in field) {
            if (item['id'] == id) {
                if (hasValue) {
                    item['value'] = n;
                    item['synonyms'] = s;
                } else {
                    field.remove(field.indexOf(item));
                }
                return;
            }
        }

        if (hasValue) {
            def ref = attributes['_']?.get(name);
            if (ref instanceof List) {
                for (item in ref) {
                    if (item['id'] == id) {
                        field.add(["id": id, "value": n, "synonyms": s]);
                        return;
                    }
                }
            }
        }
        return;
    }

    if (field instanceof Map) {
        if (field['id'] == id) {
            if (hasValue) {
                field['value'] = n;
                field['synonyms'] = s;
            } else {
                node.remove(name);
            }
        }
    } else if (hasValue) {
        def ref = attributes['_']?.get(name);

        if (ref instanceof Map) {
            if (ref['id'] == id) {
                node[name] = ["id": id, "value": n, "synonyms": s];
            }
        }
    }
}

EOF, AttributeInterface::ATTRIBUTES_FIELD).implode("\n", $calls),
                'params' => array_merge($params, [
                    '_id' => $id,
                ]),
                'lang' => 'painless',
            ]
        );
    }
}
