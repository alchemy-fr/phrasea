<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Attribute\AttributeInterface;
use App\Elasticsearch\ESFacetInterface;
use App\Entity\Core\AttributeEntity;
use App\Repository\Core\AttributeEntityRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class EntityAttributeType extends TextAttributeType
{
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

            return array_filter(array_map(function ($v) use ($entityId): ?array {
                if (empty($v)) {
                    return null;
                }

                return [
                    'id' => $entityId,
                    'value' => $v,
                ];
            }, $locales));
        }

        return null;
    }

    public function getElasticSearchTextSubField(): ?string
    {
        return 'value';
    }

    public function normalizeBucket(array $bucket): ?array
    {
        $entity = $this->getEntityFromValue($bucket['key']);
        if (null === $entity) {
            return null;
        }

        $bucket['key'] = [
            'value' => $bucket['key'],
            'label' => $entity->getValue(),
            'item' => [
                'id' => $entity->getId(),
                'value' => $entity->getValue(),
                'translations' => $entity->getTranslations(),
            ],
        ];

        return $bucket;
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

    public function denormalizeValue(?string $value): ?array
    {
        $entity = $this->getEntityFromValue($value);
        if (!$entity instanceof AttributeEntity) {
            return null;
        }

        $id = $entity->getId();
        $v = $entity->getValue();

        return [
            'id' => $id,
            'value' => $v,
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
