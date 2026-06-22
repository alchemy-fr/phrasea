<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Elasticsearch\ESFacetInterface;
use App\Model\GeoPoint;

final class GeoPointAttributeType extends AbstractAttributeType
{
    final public const string NAME = 'geo_point';
    private const string GEO_POINT_REGEX = '#^(-?\d+(?:[.]\d+)?)\s*[;,\s]\s*(-?\d+(?:[.]\d+)?)$#';

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

    public function normalizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            if (isset($value['lat']) && isset($value['lon'])) {
                return new GeoPoint($value['lat'], $value['lon']);
            } elseif (isset($value['lat']) && isset($value['lng'])) {
                return new GeoPoint($value['lat'], $value['lng']);
            }
        } elseif (is_string($value)) {
            $value = trim($value);
            if (1 === preg_match(self::GEO_POINT_REGEX, $value, $matches)) {
                return new GeoPoint((float) $matches[1], (float) $matches[2]);
            }
        }

        return parent::normalizeValue($value);
    }

    public function validate(mixed $value): ?array
    {
        if ($value instanceof GeoPoint) {
            return null;
        } elseif (null === $value) {
            return null;
        }

        return ['Invalid Geo point'];
    }

    public function convertToDbValue(mixed $value): ?string
    {
        if ($value instanceof GeoPoint) {
            return sprintf('%g,%g', $value->latitude, $value->longitude);
        }

        return parent::convertToDbValue($value);
    }

    /**
     * @return GeoPoint|null
     */
    public function denormalizeValue(?string $value): mixed
    {
        if (null === $value) {
            return null;
        }

        if (empty(trim($value))) {
            return null;
        }

        if (!str_contains($value, ' ') && !str_contains($value, ',') && !str_contains($value, ';')
        ) {
            return null;
        }

        if (1 === preg_match(self::GEO_POINT_REGEX, $value, $matches)) {
            return new GeoPoint((float) $matches[1], (float) $matches[2]);
        }

        return null;
    }

    public function getStringValue(?string $value, ?string $locale): string
    {
        $value = $this->denormalizeValue($value);
        if ($value instanceof GeoPoint) {
            return sprintf('%g,%g', $value->latitude, $value->longitude);
        }

        return '';
    }

    public function normalizeElasticsearchValue(?string $value): mixed
    {
        $value = $this->denormalizeValue($value);
        if ($value instanceof GeoPoint) {
            return [
                'lat' => $value->latitude,
                'lon' => $value->longitude,
            ];
        }

        return null;

    }
}
