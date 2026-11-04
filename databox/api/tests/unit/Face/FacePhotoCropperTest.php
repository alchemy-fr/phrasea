<?php

declare(strict_types=1);

namespace App\Tests\Unit\Face;

use App\Integration\Core\FaceRecognition\FacePhotoCropper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FacePhotoCropperTest extends TestCase
{
    #[DataProvider('provideSquares')]
    public function testSquare(array $box, int $width, int $height, array $expected): void
    {
        $this->assertSame($expected, FacePhotoCropper::square($box, $width, $height));
    }

    public static function provideSquares(): iterable
    {
        yield 'centered box: 1.8 times its longest side, around its center' => [
            ['x' => 0.4, 'y' => 0.4, 'w' => 0.2, 'h' => 0.1],
            1000,
            1000,
            ['x' => 320, 'y' => 270, 'side' => 360],
        ];

        yield 'box at the top left corner: the square shifts inside the image' => [
            ['x' => 0.0, 'y' => 0.0, 'w' => 0.1, 'h' => 0.1],
            1000,
            1000,
            ['x' => 0, 'y' => 0, 'side' => 180],
        ];

        yield 'box at the bottom right corner' => [
            ['x' => 0.9, 'y' => 0.95, 'w' => 0.1, 'h' => 0.05],
            1000,
            1000,
            ['x' => 820, 'y' => 820, 'side' => 180],
        ];

        yield 'box larger than the image / 1.8: capped at the shorter side' => [
            ['x' => 0.1, 'y' => 0.0, 'w' => 0.8, 'h' => 1.0],
            800,
            600,
            ['x' => 100, 'y' => 0, 'side' => 600],
        ];

        yield 'non-square image: the box is relative to each dimension' => [
            ['x' => 0.5, 'y' => 0.25, 'w' => 0.1, 'h' => 0.2],
            2000,
            1000,
            ['x' => 920, 'y' => 170, 'side' => 360],
        ];

        yield 'tiny box: at least one pixel' => [
            ['x' => 0.5, 'y' => 0.5, 'w' => 0.0, 'h' => 0.0],
            100,
            100,
            ['x' => 50, 'y' => 50, 'side' => 1],
        ];
    }
}
