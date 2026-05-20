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
final readonly class AttributeEntityMergeHandler
{
    public function __construct(
        private ElasticSearchClient $elasticSearchClient,
        private AttributeDefinitionRepository $attributeDefinitionRepository,
        private AttributeEntityRepository $attributeEntityRepository,
        private FieldNameResolver $fieldNameResolver,
    ) {
    }

    public function __invoke(AttributeEntityMerge $message): void
    {
        $id = $message->getId();
        /** @var AttributeEntity $mainEntity */
        $mainEntity = DoctrineUtil::findStrictByRepo($this->attributeEntityRepository, $id);
        $workspaceId = $mainEntity->getWorkspaceId();
        $definitions = $this->attributeDefinitionRepository->getWorkspaceDefinitionOfEntity(
            $mainEntity->getWorkspaceId(),
            $mainEntity->getList()->getId(),
        );

        $merged = $message->getMerged();
        $this->updateAttributeIndex($mainEntity, $merged);

        $fields = [];
        $calls = [];
        $params = [
            'merged' => $merged,
        ];
        $locales = $message->getLocales();
        foreach ($definitions as $definition) {
            $fieldName = $this->fieldNameResolver->getFieldNameFromDefinition($definition);

            foreach ($locales as $locale) {
                $fields[sprintf('%s.%s.%s', AttributeInterface::ATTRIBUTES_FIELD, AttributeInterface::NO_LOCALE, $fieldName)] = true;
                $params[$locale] = [
                    'v' => $mainEntity->getTranslations()[$locale] ?? (AttributeInterface::NO_LOCALE === $locale ? $mainEntity->getValue() : ''),
                    's' => $mainEntity->getSynonymsOfLocale($locale) ?? [],
                ];
                $calls[$fieldName.'-'.$locale] = sprintf(
                    'merge(ctx._source, \'%1$s\', \'%2$s\', params[\'_id\'], params[\'merged\'], params[\'%1$s\'][\'v\'], params[\'%1$s\'][\'s\'], %3$s);',
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
                    'should' => array_map(function (string $field) use ($id, $merged): array {
                        return [
                            'terms' => [
                                $field.'.id' => array_merge([$id], $merged),
                            ],
                        ];
                    }, array_keys($fields)),
                ],
            ],
            [
                'source' => sprintf(<<<EOF
void merge(HashMap src, String locale, String name, String id, List merged, String n, def s, boolean m) {
    HashMap attributes;

    List all = new ArrayList(merged);
    all.add(id);

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
            if (all.contains(item['id'])) {
                if (hasValOrSyn) {
                    item['id'] = id;
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
        if (all.contains(field['id'])) {
            if (hasValOrSyn) {
                field['id'] = id;
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

    private function updateAttributeIndex(AttributeEntity $mainEntity, array $merged): void
    {
        $this->elasticSearchClient->updateByQuery(
            'attribute',
            [
                'terms' => [
                    'entityId' => array_merge([$mainEntity->getId()], $merged),
                ],
            ],
            [
                'source' => 'ctx._source.entityId = params.id; ctx._source.suggestion = params.value;',
                'lang' => 'painless',
                'params' => [
                    'id' => $mainEntity->getId(),
                    'value' => $mainEntity->getValue(),
                ],
            ]
        );
    }
}
