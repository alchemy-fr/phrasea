<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Attribute\Type\EntityAttributeType;
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

        $locales = array_unique(array_merge(
            [$attributeEntity->getLocale()],
            array_keys($attributeEntity->getTranslations()),
        ));

        $fields = [];
        foreach ($definitions as $definition) {
            $fieldName = $this->fieldNameResolver->getFieldNameFromDefinition($definition);
            foreach ($locales as $locale) {
                $fields[] = sprintf('attributes.%s.%s', $locale, $fieldName);
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
    void iterateAllFields(def x, def id) {
        if (x instanceof List) {
            x.removeIf(item -> item['id'] == id);
            return;
        }

        if (!(x instanceof Map)) {
            return;
        }

        if (x['id'] == id) {
            x = null;
        }
    }

EOF.implode("\n", array_map(function (string $field): string {
        return 'iterateAllFields(ctx._source.'.$field.', params[\'id\']);';
                    }, $fields)),
                'params' => [
                    'id' => $id,
                ],
                'lang' => 'painless',
            ]
        );
    }
}
