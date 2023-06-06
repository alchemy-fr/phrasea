<?php

declare(strict_types=1);

namespace App\Image;

use Intervention\Image\ImageManager;

final class ImageManagerFactory
{
    public function createManager(array $options = []): ImageManager
    {
        return new ImageManager(array_merge(
            ['driver' => 'imagick'],
            $options
        ));
    }
}
