<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Elasticsearch\ESFacetInterface;
use App\Elasticsearch\SearchType;

abstract class AbstractAttributeType implements AttributeTypeInterface
{
    public function normalizeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return trim($value) ?: null;
        }

        return $value;
    }

    public function convertToDbValue(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        try {
            return (string) $value;
        } catch (\Throwable) {
            return null;
        }
    }

    public function denormalizeValue(?string $value): mixed
    {
        return $value;
    }

    public function getStringValue(?string $value, ?string $locale): string
    {
        return (string) $this->denormalizeValue($value);
    }

    public function normalizeElasticsearchValue(?string $value): mixed
    {
        return $value;
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

    public function validate(mixed $value): ?array
    {
        return null;
    }

    public function supportsAggregation(): bool
    {
        return false;
    }

    public function getAggregationField(): ?string
    {
        return null;
    }

    public function normalizeBuckets(array $buckets): array
    {
        return $buckets;
    }

    public function getElasticSearchTextSubField(): ?string
    {
        return null;
    }

    public function getElasticSearchSearchType(): ?SearchType
    {
        return null;
    }

    public function getAdditionalSubFields(int $boost): array
    {
        return [];
    }

    public function supportsElasticSearchFuzziness(): bool
    {
        return false;
    }

    public function isListed(): bool
    {
        return true;
    }

    public function getElasticSearchRawField(): ?string
    {
        return null;
    }

    public function getElasticSearchSortSubField(): ?string
    {
        return null;
    }
}
