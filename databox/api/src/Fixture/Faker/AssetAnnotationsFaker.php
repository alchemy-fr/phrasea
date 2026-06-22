<?php

declare(strict_types=1);

namespace App\Fixture\Faker;

use App\Entity\Traits\AssetAnnotationsInterface;
use Faker\Provider\Base as BaseProvider;

class AssetAnnotationsFaker extends BaseProvider
{
    public function assetAnnotationsCue(): array
    {
        return [
            [
                'type' => AssetAnnotationsInterface::TYPE_CUE,
                't' => random_int(0, 10),
            ],
        ];
    }

    public function assetAnnotationsCircle(): array
    {
        $x = random_int(5, 100) / 100;
        $y = random_int(5, 100) / 100;

        return [
            [
                'type' => AssetAnnotationsInterface::TYPE_CIRCLE,
                'x' => $x,
                'y' => $y,
                'r' => min(1 - $x, 1 - $y, random_int(5, 50) / 100),
                'c' => $this->randomColor(),
                's' => $this->randomSize(),
                'page' => 2,
            ],
        ];
    }

    public function assetAnnotationsTarget(): array
    {
        $x = random_int(0, 100) / 100;
        $y = random_int(0, 100) / 100;

        return [
            [
                'type' => AssetAnnotationsInterface::TYPE_TARGET,
                'x' => $x,
                'y' => $y,
                'c' => $this->randomColor(),
                's' => $this->randomSize(),
                'page' => 2,
            ],
        ];
    }

    public function assetAnnotationsRect(): array
    {
        $x1 = random_int(0, 100) / 100;
        $y1 = random_int(0, 100) / 100;
        $x2 = min($x1 * (1 + random_int(10, 100) / 100), 1);
        $y2 = min($y1 * (1 + random_int(10, 100) / 100), 1);

        return [
            [
                'type' => AssetAnnotationsInterface::TYPE_RECTANGLE,
                'x' => $x1,
                'y' => $y1,
                'w' => $x2 - $x1,
                'h' => $y2 - $y1,
                'c' => $this->randomColor(),
                's' => $this->randomSize(),
                'page' => 2,
            ],
        ];
    }

    private function randomColor(): string
    {
        $rouge = dechex(random_int(0, 255));
        $vert = dechex(random_int(0, 255));
        $bleu = dechex(random_int(0, 255));

        $rouge = str_pad($rouge, 2, '0', STR_PAD_LEFT);
        $vert = str_pad($vert, 2, '0', STR_PAD_LEFT);
        $bleu = str_pad($bleu, 2, '0', STR_PAD_LEFT);

        return '#'.$rouge.$vert.$bleu;
    }

    private function randomSize(): float
    {
        return (random_int(1, 10) / 2) / 100;
    }
}
