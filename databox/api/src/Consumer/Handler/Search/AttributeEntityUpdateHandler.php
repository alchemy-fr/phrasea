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

        $this->updateAttributeIndex($attributeEntity);

        $fields = [];
        $calls = [];
        $params = [];
        $locales = $message->getLocales();

        if (empty($locales)) {
            return;
        }

        foreach ($definitions as $definition) {
            $fieldName = $this->fieldNameResolver->getFieldNameFromDefinition($definition);
            $fields[sprintf('%s.%s.%s', AttributeInterface::ATTRIBUTES_FIELD, AttributeInterface::NO_LOCALE, $fieldName)] = true;

            foreach ($locales as $locale) {
                $params[$locale] = [
                    'v' => $attributeEntity->getTranslations()[$locale] ?? (AttributeInterface::NO_LOCALE === $locale ? $attributeEntity->getValue() : ''),
                    's' => $attributeEntity->getSynonymsOfLocale($locale) ?? [],
                ];
                $calls[$fieldName.'-'.$locale] = sprintf(
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

    boolean hasVal = !n.isEmpty();
    boolean hasSyn = s.size() > 0;
    boolean hasValOrSyn = hasVal || hasSyn;

    HashMap node = attributes.get(locale);
    if (!(node instanceof Map)) {
        node = attributes[locale] = [:];
    }
    def field = node.get(name);

    if (m) {
        if (!(field instanceof List)) {
            return;
        }
        for (item in field) {
            if (item['id'] == id) {
                if (hasValOrSyn) {
                    if (hasVal) {
                        item['value'] = n;
                    } else {
                        item.remove('value');
                    }

                    if (hasSyn) {
                        item['synonyms'] = s;
                    } else {
                        item.remove('synonyms');
                    }
                } else {
                    field.remove(field.indexOf(item));
                }
            }
        }

        return;
    }

    if (field instanceof Map) {
        if (field['id'] == id) {
            if (hasValOrSyn) {
                if (hasVal) {
                    field['value'] = n;
                } else {
                    field.remove('value');
                }

                if (hasSyn) {
                    field['synonyms'] = s;
                } else {
                    field.remove('synonyms');
                }
            } else {
                node.remove(name);
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

    private function updateAttributeIndex(AttributeEntity $attributeEntity): void
    {
        $this->elasticSearchClient->updateByQuery(
            'attribute',
            [
                'term' => [
                    'entityId' => $attributeEntity->getId(),
                ],
            ],
            [
                // Change "suggestion" field to new value
                'source' => 'ctx._source.suggestion = params.value;',
                'lang' => 'painless',
                'params' => [
                    'value' => $attributeEntity->getValue(),
                ],
            ]
        );
    }
}
