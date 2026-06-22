<?php

declare(strict_types=1);

namespace App\Model;

final readonly class GeoPoint
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
    }
}
