<?php

declare(strict_types=1);

namespace App\Model;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class GeoPoint
{
    public function __construct(
        #[SerializedName('lat')]
        #[Groups(['_'])]
        public float $latitude,
        #[SerializedName('lng')]
        #[Groups(['_'])]
        public float $longitude,
    ) {
    }
}
