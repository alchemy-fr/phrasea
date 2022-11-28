<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Elasticsearch\FacetInterface;
use App\Entity\Core\AttributeDefinition;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

abstract class AbstractAttributeType implements AttributeTypeInterface
{
    public function normalizeValue($value)
    {
        return $value;
    }

    public function getFacetType(): string
    {
        return FacetInterface::TYPE_STRING;
    }

    public function denormalizeValue($value)
    {
        return $value;
    }

    public function isLocaleAware(): bool
    {
        return false;
    }

    public function getGroupValueLabel($value): ?string
    {
        if (null === $value) {
            return null;
        }

        return (string) $value;
    }

    public function getElasticSearchMapping(string $locale, AttributeDefinition $definition): array
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

    public function getAggregationField(): ?string
    {
        return null;
    }
}
