<?php

declare(strict_types=1);

namespace App\Elasticsearch\Geo;

final readonly class GeoCluster
{
    /**
     * @param array{top: float, left: float, bottom: float, right: float} $bounds  the bounding box of the clustered points
     * @param string|null                                                 $assetId the asset, when the cluster holds a single one
     */
    public function __construct(
        public string $key,
        public int $count,
        public float $lat,
        public float $lng,
        public array $bounds,
        public ?string $assetId = null,
    ) {
    }
}
