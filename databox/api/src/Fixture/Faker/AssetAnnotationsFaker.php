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
                't' => rand(0, 10),
            ],
        ];
    }

    public function assetAnnotationsCircle(): array
    {
        $x = rand(5, 100) / 100;
        $y = rand(5, 100) / 100;

        return [
            [
                'type' => AssetAnnotationsInterface::TYPE_CIRCLE,
                'x' => $x,
                'y' => $y,
                'r' => min(1 - $x, 1 - $y, rand(5, 50) / 100),
                'c' => $this->randomColor(),
                's' => $this->randomSize(),
                'page' => 2,
            ],
        ];
    }

    public function assetAnnotationsPoint(): array
    {
        $x = rand(0, 100) / 100;
        $y = rand(0, 100) / 100;

        return [
            [
                'type' => AssetAnnotationsInterface::TYPE_POINT,
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
        $x1 = rand(0, 100) / 100;
        $y1 = rand(0, 100) / 100;
        $x2 = min($x1 * (1 + rand(10, 100) / 100), 1);
        $y2 = min($y1 * (1 + rand(10, 100) / 100), 1);

        return [
            [
                'type' => AssetAnnotationsInterface::TYPE_RECTANGLE,
                'x1' => $x1,
                'y1' => $y1,
                'x2' => $x2,
                'y2' => $y2,
                'c' => $this->randomColor(),
                's' => $this->randomSize(),
                'page' => 2,
            ],
        ];
    }

    private function randomColor(): string
    {
        $rouge = dechex(rand(0, 255));
        $vert = dechex(rand(0, 255));
        $bleu = dechex(rand(0, 255));

        $rouge = str_pad($rouge, 2, '0', STR_PAD_LEFT);
        $vert = str_pad($vert, 2, '0', STR_PAD_LEFT);
        $bleu = str_pad($bleu, 2, '0', STR_PAD_LEFT);

        return '#'.$rouge.$vert.$bleu;
    }

    private function randomSize(): float
    {
        return (rand(1, 10) / 2) / 100;
    }
}
