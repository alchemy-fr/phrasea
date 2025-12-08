<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\CoreBundle\Util\LocaleUtil;
use App\Api\Traits\UserLocaleTrait;
use App\Attribute\AttributeInterface;
use App\Elasticsearch\ESFacetInterface;
use App\Entity\Core\AttributeEntity;
use App\Repository\Core\AttributeEntityRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class EntityAttributeType extends TextAttributeType
{
    use UserLocaleTrait;

    public const string NAME = 'entity';

    public function __construct(
        private readonly AttributeEntityRepository $repository,
    ) {
    }

    public static function getName(): string
    {
        return self::NAME;
    }

    public function supportsTranslations(): bool
    {
        return true;
    }

    public function validate($value, ExecutionContextInterface $context): void
    {
        if (null === $value) {
            return;
        }

        if (!Uuid::isValid($value)) {
            $context->addViolation('Invalid entity ID');
        }
    }

    public function normalizeElasticsearchValue(?string $value): string|array|null
    {
        $entity = $this->getEntityFromValue($value);
        if ($entity instanceof AttributeEntity) {
            $locales = array_merge($entity->getTranslations() ?? [], [
                AttributeInterface::NO_LOCALE => $entity->getValue(),
            ]);
            $entityId = $entity->getId();

            $output = [];

            foreach ($locales as $locale => $v) {
                if (empty($v)) {
                    continue;
                }

                $output[$locale] = [
                    'id' => $entityId,
                    'value' => $v,
                ];
            }

            $synonyms = $entity->getSynonyms();
            if (!empty($synonyms)) {
                foreach ($synonyms as $locale => $synonym) {
                    if (empty($synonym)) {
                        continue;
                    }

                    $output[$locale] ??= [
                        'id' => $entityId,
                    ];
                    $output[$locale]['synonyms'] = $synonym;
                }
            }

            return $output;
        }

        return null;
    }

    public function getElasticSearchTextSubField(): ?string
    {
        return 'value';
    }

    public function getAdditionalSubFields(int $boost): array
    {
        return [
            'synonyms' => $boost * 0.5,
        ];
    }

    public function normalizeBuckets(array $buckets): array
    {
        $entities = DoctrineUtil::getIndexFromIds($this->repository, array_map(fn ($b) => $b['key'], $buckets));

        return array_map(function (array $bucket) use ($entities): ?array {
            $entity = $entities[$bucket['key']] ?? null;
            if (null === $entity) {
                return null;
            }

            $translatedValue = $this->getTranslatedValue($entity);

            $bucket['key'] = [
                'value' => $bucket['key'],
                'label' => $translatedValue,
                'item' => [
                    'id' => $entity->getId(),
                    'value' => $entity->getValue(),
                    'translatedValue' => $translatedValue,
                ],
            ];

            return $bucket;
        }, $buckets);
    }

    protected function getTranslatedValue(AttributeEntity $entity): ?string
    {
        $translations = $entity->getTranslations() ?? [];
        $locale = LocaleUtil::getBestLocale(array_keys($translations), $this->getPreferredLocales($entity->getWorkspace()));

        return $translations[$locale] ?? $entity->getValue();
    }

    public function getAggregationField(): ?string
    {
        return 'id';
    }

    public function normalizeValue($value): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof AttributeEntity) {
            return $value->getId();
        }

        return $value;
    }

    private function getEntityFromValue(?string $value): ?AttributeEntity
    {
        if (null === $value) {
            return null;
        }

        return $this->repository->find($value);
    }

    public function denormalizeValue(?string $value)
    {
        $entity = $this->getEntityFromValue($value);
        if (!$entity instanceof AttributeEntity) {
            return null;
        }

        return [
            'id' => $entity->getId(),
            'value' => $this->getTranslatedValue($entity),
            'createdAt' => $entity->getCreatedAt(),
        ];
    }

    public function getElasticSearchMapping(string $locale): ?array
    {
        $mapping = parent::getElasticSearchMapping($locale);

        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'keyword',
                ],
                'value' => [
                    ...$mapping,
                    'type' => $this->getElasticSearchType(),
                ],
                'synonyms' => [
                    'type' => 'text',
                ],
            ],
        ];
    }

    public function getFacetType(): string
    {
        return ESFacetInterface::TYPE_ENTITY;
    }

    public function getElasticSearchRawField(): ?string
    {
        return 'id';
    }
}
