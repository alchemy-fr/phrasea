<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Attribute\AttributeInterface;
use App\Elasticsearch\ElasticSearchClient;
use App\Elasticsearch\Mapping\FieldNameResolver;
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
                'del(ctx._source.%2$s[0], \'%1$s\');',
                $fieldName,
                AttributeInterface::ATTRIBUTES_FIELD
            );
        }

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
                'source' => <<<EOF
void del(HashMap c, String name) {
    for (def entry : c.entrySet()) {
        String locale = entry.getKey();
        if (c[locale].get(name) instanceof List || c[locale].get(name) instanceof Map) {
            c[locale].remove(name);
        }
    }
}

EOF.implode("\n", $calls),
                'lang' => 'painless',
            ]
        );
    }
}
