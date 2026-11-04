<?php

declare(strict_types=1);

namespace App\Elasticsearch\Geo;

final readonly class GeoClusterResult
{
    /**
     * @param int                                                              $total      the assets matching the search
     * @param int                                                              $geolocated the assets matching the search that have a position
     * @param array{top: float, left: float, bottom: float, right: float}|null $bounds     the bounding box of all the positions, whatever the clustered area
     * @param GeoCluster[]                                                     $clusters
     */
    public function __construct(
        public int $total,
        public int $geolocated,
        public ?array $bounds,
        public array $clusters,
    ) {
    }
}
