<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Elasticsearch\SearchType;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[AutoconfigureTag(self::TAG)]
interface AttributeTypeInterface
{
    final public const string TAG = 'app.attribute_type';
    final public const string RAW_PROP = 'raw';

    public static function getName(): string;

    public function getElasticSearchType(): string;

    public function getElasticSearchTextSubField(): ?string;

    public function getElasticSearchSearchType(): ?SearchType;

    public function getElasticSearchRawField(): ?string;

    public function supportsElasticSearchFuzziness(): bool;

    public function getFacetType(): string;

    public function supportsAggregation(): bool;

    public function getElasticSearchMapping(string $locale): ?array;

    /**
     * Normalize value for database.
     */
    public function normalizeValue(mixed $value): ?string;

    /**
     * De-normalize value from database to PHP.
     */
    public function denormalizeValue(?string $value);

    /**
     * Format value for client.
     */
    public function getGroupValueLabel(mixed $value): ?string;

    /**
     * De-normalize value from Elasticsearch to database.
     */
    public function denormalizeElasticsearchValue(mixed $value): ?string;

    /**
     * Normalize value from database to ES index.
     */
    public function normalizeElasticsearchValue(?string $value);

    public function isMappingLocaleAware(): bool;

    public function isLocaleAware(): bool;

    public function supportsSuggest(): bool;

    public function supportsTranslations(): bool;

    public function validate($value, ExecutionContextInterface $context): void;

    public function getAggregationField(): ?string;

    public function normalizeBucket(array $bucket): ?array;

    public function isListed(): bool;
}
