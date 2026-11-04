<?php

declare(strict_types=1);

namespace App\Fixture\Faker;

use Faker\Provider\Base as BaseProvider;

/**
 * Gives every asset a real place, so that its Location, City and Country attributes match.
 * The weights concentrate the assets in a few cities (clusters on the map), and the jitter
 * spreads them in the city so that zooming in separates them.
 */
class GeoPlaceFaker extends BaseProvider
{
    /**
     * @var list<array{city: string, country: string, lat: float, lng: float, radius: float, weight: int}>
     */
    private const array PLACES = [
        ['city' => 'Paris', 'country' => 'France', 'lat' => 48.8566, 'lng' => 2.3522, 'radius' => 0.06, 'weight' => 12],
        ['city' => 'Lyon', 'country' => 'France', 'lat' => 45.7640, 'lng' => 4.8357, 'radius' => 0.04, 'weight' => 4],
        ['city' => 'Marseille', 'country' => 'France', 'lat' => 43.2965, 'lng' => 5.3698, 'radius' => 0.04, 'weight' => 3],
        ['city' => 'Bordeaux', 'country' => 'France', 'lat' => 44.8378, 'lng' => -0.5792, 'radius' => 0.03, 'weight' => 2],
        ['city' => 'Nantes', 'country' => 'France', 'lat' => 47.2184, 'lng' => -1.5536, 'radius' => 0.03, 'weight' => 2],
        ['city' => 'Chamonix', 'country' => 'France', 'lat' => 45.9237, 'lng' => 6.8694, 'radius' => 0.02, 'weight' => 1],
        ['city' => 'London', 'country' => 'United Kingdom', 'lat' => 51.5074, 'lng' => -0.1278, 'radius' => 0.06, 'weight' => 5],
        ['city' => 'Berlin', 'country' => 'Germany', 'lat' => 52.5200, 'lng' => 13.4050, 'radius' => 0.05, 'weight' => 3],
        ['city' => 'Amsterdam', 'country' => 'Netherlands', 'lat' => 52.3676, 'lng' => 4.9041, 'radius' => 0.03, 'weight' => 2],
        ['city' => 'Barcelona', 'country' => 'Spain', 'lat' => 41.3874, 'lng' => 2.1686, 'radius' => 0.04, 'weight' => 3],
        ['city' => 'Madrid', 'country' => 'Spain', 'lat' => 40.4168, 'lng' => -3.7038, 'radius' => 0.04, 'weight' => 2],
        ['city' => 'Rome', 'country' => 'Italy', 'lat' => 41.9028, 'lng' => 12.4964, 'radius' => 0.04, 'weight' => 3],
        ['city' => 'Geneva', 'country' => 'Switzerland', 'lat' => 46.2044, 'lng' => 6.1432, 'radius' => 0.02, 'weight' => 1],
        ['city' => 'Reykjavik', 'country' => 'Iceland', 'lat' => 64.1466, 'lng' => -21.9426, 'radius' => 0.03, 'weight' => 1],
        ['city' => 'New York', 'country' => 'United States', 'lat' => 40.7128, 'lng' => -74.0060, 'radius' => 0.06, 'weight' => 4],
        ['city' => 'San Francisco', 'country' => 'United States', 'lat' => 37.7749, 'lng' => -122.4194, 'radius' => 0.04, 'weight' => 2],
        ['city' => 'Montreal', 'country' => 'Canada', 'lat' => 45.5019, 'lng' => -73.5674, 'radius' => 0.04, 'weight' => 2],
        ['city' => 'Mexico City', 'country' => 'Mexico', 'lat' => 19.4326, 'lng' => -99.1332, 'radius' => 0.05, 'weight' => 1],
        ['city' => 'Rio de Janeiro', 'country' => 'Brazil', 'lat' => -22.9068, 'lng' => -43.1729, 'radius' => 0.05, 'weight' => 2],
        ['city' => 'Buenos Aires', 'country' => 'Argentina', 'lat' => -34.6037, 'lng' => -58.3816, 'radius' => 0.05, 'weight' => 1],
        ['city' => 'Dakar', 'country' => 'Senegal', 'lat' => 14.7167, 'lng' => -17.4677, 'radius' => 0.03, 'weight' => 1],
        ['city' => 'Nairobi', 'country' => 'Kenya', 'lat' => -1.2921, 'lng' => 36.8219, 'radius' => 0.04, 'weight' => 1],
        ['city' => 'Cape Town', 'country' => 'South Africa', 'lat' => -33.9249, 'lng' => 18.4241, 'radius' => 0.04, 'weight' => 1],
        ['city' => 'Mumbai', 'country' => 'India', 'lat' => 19.0760, 'lng' => 72.8777, 'radius' => 0.05, 'weight' => 1],
        ['city' => 'Tokyo', 'country' => 'Japan', 'lat' => 35.6762, 'lng' => 139.6503, 'radius' => 0.06, 'weight' => 3],
        ['city' => 'Sydney', 'country' => 'Australia', 'lat' => -33.8688, 'lng' => 151.2093, 'radius' => 0.05, 'weight' => 2],
    ];

    /**
     * @var array<string, array{city: string, country: string, lat: float, lng: float}>
     */
    private array $assigned = [];

    /**
     * Returns "lat,lng", the format geo point attributes are stored in.
     */
    public function geoPlaceLocation(mixed $subject): string
    {
        $place = $this->getPlace($subject);

        return sprintf('%g,%g', $place['lat'], $place['lng']);
    }

    public function geoPlaceCity(mixed $subject): string
    {
        return $this->getPlace($subject)['city'];
    }

    public function geoPlaceCountry(mixed $subject): string
    {
        return $this->getPlace($subject)['country'];
    }

    /**
     * @return array{city: string, country: string, lat: float, lng: float}
     */
    private function getPlace(mixed $subject): array
    {
        $key = is_object($subject) ? (string) spl_object_id($subject) : (string) $subject;

        return $this->assigned[$key] ??= $this->pickPlace();
    }

    /**
     * @return array{city: string, country: string, lat: float, lng: float}
     */
    private function pickPlace(): array
    {
        $roll = self::numberBetween(1, array_sum(array_column(self::PLACES, 'weight')));
        foreach (self::PLACES as $place) {
            $roll -= $place['weight'];
            if ($roll <= 0) {
                break;
            }
        }

        $angle = self::randomFloat(4, 0, 2 * M_PI);
        $distance = $place['radius'] * sqrt(self::randomFloat(4, 0, 1));

        return [
            'city' => $place['city'],
            'country' => $place['country'],
            'lat' => round($place['lat'] + $distance * sin($angle), 5),
            'lng' => round($place['lng'] + $distance * cos($angle) / cos(deg2rad($place['lat'])), 5),
        ];
    }
}
