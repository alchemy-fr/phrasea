<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use Elastica\Query;
use Elastica\Query\AbstractQuery;

class GeoPointAttributeType extends AbstractAttributeType
{
    public const NAME = 'geo_point';

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getElasticSearchType(): string
    {
        return 'ip';
    }

    public function supportsAggregation(): bool
    {
        return true;
    }

    public function normalizeValue($value): ?string
    {
        if (is_array($value)) {
            return sprintf('%f, %f', $value['lat'], $value['lng']);
        }

        return $value;
    }

    public function denormalizeValue(?string $value)
    {
        if (null === $value) {
            return null;
        }

        [$lat, $lng] = explode(',', $value);

        return [
            'lng' => (float) trim($lng),
            'lat' => (float) trim($lat),
        ];
    }

    public function createFilterQuery(string $field, $value): AbstractQuery
    {
        return new Query\GeoDistance($field, $value, '200m');
    }
}
