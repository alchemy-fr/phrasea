<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use Symfony\Component\Validator\Context\ExecutionContextInterface;

abstract class AbstractAttributeType implements AttributeTypeInterface
{
    public function normalizeValue($value)
    {
        return $value;
    }

    public function denormalizeValue($value)
    {
        return (string) $value;
    }

    public function isLocaleAware(): bool
    {
        return false;
    }

    public function getElasticSearchMapping(string $language): array
    {
        return [];
    }

    public function validate($value, ExecutionContextInterface $context): void
    {
    }

    public function supportsAggregation(): bool
    {
        return false;
    }
}
