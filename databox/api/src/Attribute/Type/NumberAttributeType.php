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

    public function normalizeValue(mixed $value): mixed
    {
        if (is_numeric($value)) {
            return $value + 0; // Convert to int or float
        }

        return parent::normalizeValue($value);
    }

    public function denormalizeValue(?string $value): mixed
    {
        if (is_numeric($value)) {
            return $value + 0; // Convert to int or float
        }

        return null;
    }

    /**
     * @param int|float|string $value
     */
    public function normalizeElasticsearchValue($value): mixed
    {
        if (is_numeric($value)) {
            return $value + 0; // Convert to int or float
        }

        return null;
    }

    public function validate(mixed $value): ?array
    {
        if (!is_numeric($value)) {
            return ['Invalid number'];
        }

        return null;
    }

    public function supportsAggregation(): bool
    {
        return true;
    }
}
