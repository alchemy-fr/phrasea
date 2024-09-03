<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Elasticsearch\ESFacetInterface;
use App\Elasticsearch\SearchType;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

abstract class AbstractAttributeType implements AttributeTypeInterface
{
    public function normalizeValue($value): ?string
    {
        if (null === $value) {
            return null;
        }

        try {
            return (string) $value;
        } catch (\Throwable) {
            return null;
        }
    }

    public function denormalizeValue(?string $value)
    {
        return $value;
    }

    public function normalizeElasticsearchValue(?string $value)
    {
        return $value;
    }

    public function denormalizeElasticsearchValue($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return (string) $value;
    }

    public function getFacetType(): string
    {
        return ESFacetInterface::TYPE_TEXT;
    }

    public function isMappingLocaleAware(): bool
    {
        return false;
    }

    public function isLocaleAware(): bool
    {
        return false;
    }

    public function supportsSuggest(): bool
    {
        return false;
    }

    public function supportsTranslations(): bool
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

    public function getElasticSearchMapping(string $locale): ?array
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

    public function normalizeBucket(array $bucket): ?array
    {
        return $bucket;
    }

    public function getElasticSearchSubField(): ?string
    {
        return null;
    }

    public function getElasticSearchSearchType(): ?SearchType
    {
        return null;
    }

    public function supportsElasticSearchFuzziness(): bool
    {
        return false;
    }
}
