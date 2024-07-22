<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Entity\Core\AttributeEntity;
use App\Repository\Core\AttributeEntityRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class EntityAttributeType extends TextAttributeType
{
    public const NAME = 'entity';

    public function __construct(
        private AttributeEntityRepository $repository,
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
            $locales = $entity->getTranslations() ?? [];
            $locales[$entity->getLocale()] = $entity->getValue();

            return $locales;
        }

        return null;
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
}
