<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Elasticsearch\SearchType;

class NumberAttributeType extends AbstractAttributeType
{
    public const string NAME = 'number';

    public static function getName(): string
    {
        return static::NAME;
    }

    public function getElasticSearchMapping(string $locale): ?array
    {
        return [
            'fields' => [
                AttributeTypeInterface::RAW_PROP => [
                    'type' => 'keyword',
                ],
            ],
        ];
    }

    public function getElasticSearchSearchType(): ?SearchType
    {
        return SearchType::Match;
    }

    public function getElasticSearchType(): string
    {
        return 'long';
    }

    public function supportsSuggest(): bool
    {
        return false;
    }

    /**
     * @param int|float|string $value
     *
     * @return float
     */
    public function normalizeElasticsearchValue($value)
    {
        return (float) $value;
    }

    public function validate(mixed $value): ?array
    {
        if (!is_numeric($value)) {
            return ['Invalid number'];
        }

        return null;
    }

    public function denormalizeValue(?string $value): mixed
    {
        if (is_numeric($value)) {
            return $value + 0; // Convert to int or float
        }

        return $value;
    }

    public function supportsAggregation(): bool
    {
        return true;
    }
}
