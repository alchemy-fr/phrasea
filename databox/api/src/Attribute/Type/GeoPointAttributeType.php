<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Elasticsearch\ESFacetInterface;
use App\Model\GeoPoint;

final class GeoPointAttributeType extends AbstractAttributeType
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

    public function normalizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            if (isset($value['lat']) && isset($value['lon'])) {
                return new GeoPoint($value['lat'], $value['lon']);
            } elseif (isset($value['lat']) && isset($value['lng'])) {
                return new GeoPoint($value['lat'], $value['lng']);
            }
        } elseif (is_string($value)) {
            if (1 === preg_match('#^(\d+(?:[.]\d+)?),\s*(\d+(?:[.]\d+)?)$#', $value, $matches)) {
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
        if (is_array($value)) {
            if (isset($value['lat'], $value['lng'])) {
                return sprintf('%g,%g', $value['lat'], $value['lng']);
            } elseif (isset($value[0], $value[1])) {
                return sprintf('%g,%g', $value[0], $value[1]);
            }

            return null;
        }

        if (!is_string($value) || (!str_contains($value, ' ') && !str_contains($value, ','))) {
            return null;
        }

        return $this->convertToDbValue($this->denormalizeValue($value));
    }

    public function denormalizeValue(?string $value): mixed
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

        return new GeoPoint($lat, $lng);
    }

    public function getStringValue(?string $value, ?string $locale): string
    {
        $value = $this->denormalizeValue($value);
        if (null === $value) {
            return '';
        }

        return sprintf('%g,%g', $value['lat'], $value['lng']);
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
