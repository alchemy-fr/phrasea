<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Elasticsearch\SearchType;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(self::TAG)]
interface AttributeTypeInterface
{
    final public const string TAG = 'app.attribute_type';
    final public const string RAW_PROP = 'raw';

    public static function getName(): string;

    public function getElasticSearchType(): string;

    public function getElasticSearchTextSubField(): ?string;

    public function getElasticSearchSearchType(): ?SearchType;

    /**
     * @return array<string, int>
     */
    public function getAdditionalSubFields(int $boost): array;

    public function getElasticSearchRawField(): ?string;

    public function getElasticSearchSortSubField(): ?string;

    public function supportsElasticSearchFuzziness(): bool;

    public function getFacetType(): string;

    public function supportsAggregation(): bool;

    public function getElasticSearchMapping(string $locale): ?array;

    /**
     * Normalize input value, before validation.
     */
    public function normalizeValue(mixed $value): mixed;

    public function convertToDbValue(mixed $value): ?string;

    /**
     * De-normalize value from database to PHP.
     */
    public function denormalizeValue(?string $value): mixed;

    /**
     * Normalize value from database to ES index.
     */
    public function normalizeElasticsearchValue(?string $value): mixed;

    public function getStringValue(?string $value, ?string $locale): string;

    /**
     * Format value for client.
     */
    public function getGroupValueLabel(mixed $value): ?string;

    public function isMappingLocaleAware(): bool;

    public function isLocaleAware(): bool;

    public function supportsSuggest(): bool;

    public function supportsTranslations(): bool;

    /**
     * @return array Errors
     */
    public function validate(mixed $value): ?array;

    public function getAggregationField(): ?string;

    public function normalizeBuckets(array $buckets): array;

    public function isListed(): bool;
}
