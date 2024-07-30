<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Attribute\Type\EntityAttributeType;
use App\Elasticsearch\ElasticSearchClient;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Elasticsearch\Mapping\IndexMappingUpdater;
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
    )
    {
    }

    public function __invoke(AttributeEntityUpdate $message): void
    {
        /** @var AttributeEntity $attributeEntity */
        $id = $message->getId();
        $attributeEntity = DoctrineUtil::findStrictByRepo($this->attributeEntityRepository, $id);
        $definitions = $this->attributeDefinitionRepository->getWorkspaceDefinitionOfType(
            $attributeEntity->getWorkspaceId(),
            EntityAttributeType::getName(),
        );

        $fields = [];
        $calls = [];
        $params = [];
        foreach ($definitions as $definition) {
            $fieldName = $this->fieldNameResolver->getFieldNameFromDefinition($definition);
            foreach ($message->getChanges() as $locale => $change) {
                $fields[] = sprintf('%s.%s.%s', IndexMappingUpdater::ATTRIBUTES_FIELD, $locale, $fieldName);
                $params[$locale] = $change;
                $calls[$locale] = sprintf('up(ctx._source.%3$s.%1$s.%2$s, params[\'_id\'], params[\'%1$s\']);', $locale, $fieldName, IndexMappingUpdater::ATTRIBUTES_FIELD);
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
                    }, $fields),
                ],
            ],
            [
                'source' => <<<EOF
void up(def x, def id, def n) {
    if (x instanceof List) {
        for (item in x) {
            if (item['id'] == id) {
                item['value'] = n;
            }
        }
        return;
    }
    if (!(x instanceof Map)) {
        return;
    }
    if (x['id'] == id) {
        x['value'] = n;
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