<?php

declare(strict_types=1);

namespace App\Tests\Image;

use App\Service\Image\ImageDownscaler;
use App\Service\Image\ImageManagerFactory;
use PHPUnit\Framework\TestCase;

class ImageDownscalerTest extends TestCase
{
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            @unlink($file);
        }
    }

    public function testLargeImageIsDownscaledToJpeg(): void
    {
        $source = $this->createImage(4000, 3000);

        $result = $this->createDownscaler()->downscale($source, 1024);
        $this->tmpFiles[] = $result;

        $this->assertNotSame($source, $result);
        [$width, $height] = getimagesize($result);
        $this->assertSame(1024, $width);
        $this->assertSame(768, $height);
        $this->assertSame('image/jpeg', mime_content_type($result));
        $this->assertLessThan(filesize($source), filesize($result));
    }

    public function testSmallImageIsReturnedUntouched(): void
    {
        $source = $this->createImage(800, 600);

        $this->assertSame($source, $this->createDownscaler()->downscale($source, 1024));
    }

    public function testHeavyImageIsReencodedEvenWhenSmallEnough(): void
    {
        $source = $this->createImage(800, 600);

        $result = $this->createDownscaler()->downscale($source, 1024, maxBytes: 10);
        $this->tmpFiles[] = $result;

        $this->assertNotSame($source, $result);
        [$width, $height] = getimagesize($result);
        $this->assertSame(800, $width);
        $this->assertSame(600, $height);
    }

    private function createDownscaler(): ImageDownscaler
    {
        return new ImageDownscaler(new ImageManagerFactory());
    }

    private function createImage(int $width, int $height): string
    {
        $image = new \Imagick();
        $image->newImage($width, $height, new \ImagickPixel('white'));
        // Some noise so that the file has a realistic size
        $image->addNoiseImage(\Imagick::NOISE_RANDOM);
        $image->setImageFormat('png');

        $path = tempnam(sys_get_temp_dir(), 'downscale-test-').'.png';
        $image->writeImage($path);
        $this->tmpFiles[] = $path;

        return $path;
    }
}
