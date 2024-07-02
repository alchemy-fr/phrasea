<?php

declare(strict_types=1);

namespace App\Fixture\Faker;

use Faker\Provider\Base as BaseProvider;
use App\Entity\Traits\AssetAnnotationsInterface;

class AssetAnnotationsFaker extends BaseProvider
{
    public function assetAnnotations(): array
    {
        $x1 = rand(0, 100)/100;
        $y1 = rand(0, 100)/100;
        $x2 = min($x1 * (1 + rand(1, 100) / 100), 1);
        $y2 = min($x1 * (1 + rand(1, 100) / 100), 1);

        return [
            [
                'type' => AssetAnnotationsInterface::TYPE_POINT,
                'x' => rand(0, 100)/100,
                'y' => rand(0, 100)/100,
                'c' => rand(0, 1) > 0.5 ? 'red' : 'blue',
            ],
            [
                'type' => AssetAnnotationsInterface::TYPE_RECTANGLE,
                'x1' => $x1,
                'y1' => $y1,
                'x2' => $x2,
                'y2' => $y2,
                'c' => rand(0, 1) > 0.5 ? 'yellow' : 'green',
            ],
            [
                'type' => AssetAnnotationsInterface::TYPE_CUE,
                't' => rand(0, 10),
            ],
        ];
    }
}
