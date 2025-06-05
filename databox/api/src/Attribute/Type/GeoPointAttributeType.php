<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Elasticsearch\ESFacetInterface;

class GeoPointAttributeType extends AbstractAttributeType
{
    final public const string NAME = 'geo_point';

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getElasticSearchType(): string
    {
        return 'geo_point';
    }

    public function getFacetType(): string
    {
        return ESFacetInterface::TYPE_GEO_DISTANCE;
    }

    public function supportsAggregation(): bool
    {
        return true;
    }

    public function normalizeValue($value): ?string
    {
        if (is_array($value)) {
            if (isset($value['lat'], $value['lng'])) {
                return sprintf('%g,%g', $value['lat'], $value['lng']);
            } elseif (isset($value[0], $value[1])) {
                return sprintf('%g,%g', $value[0], $value[1]);
            } else {
                return null;
            }
        }

        if (!is_string($value) || (!str_contains($value, ' ') && !str_contains($value, ','))) {
            return null;
        }

        return $this->normalizeValue($this->denormalizeValue($value));
    }

    public function denormalizeValue(?string $value)
    {
        if (null === $value) {
            return null;
        }

        if (empty(trim($value))) {
            return null;
        }

        if (!is_string($value) || (!str_contains($value, ' ') && !str_contains($value, ','))) {
            return null;
        }

        [$lat, $lng] = preg_split('#\s*[, ]\s*#', $value);

        return [
            'lat' => (float) trim($lat),
            'lng' => (float) trim($lng),
        ];
    }

    public function normalizeElasticsearchValue(?string $value)
    {
        $value = $this->denormalizeValue($value);
        if (null === $value) {
            return null;
        }

        return [
            'lat' => $value['lat'],
            'lon' => $value['lng'],
        ];
    }
}
