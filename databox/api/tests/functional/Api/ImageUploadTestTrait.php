<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Consumer\Handler\Image\ProcessImageSet;
use App\Entity\Core\File;
use App\Tests\Functional\InterceptedTransports;

/**
 * Uploads of images (entity list value photos, workspace logos) and their processing.
 */
trait ImageUploadTestTrait
{
    use MultipartUploadTestTrait;

    private function createPngUpload(int $width = 1, int $height = 1, string $name = 'image.png', string $ownerId = KeycloakClientTestMock::USER_UID): MultipartUpload
    {
        return $this->createUpload(self::createImage($width, $height, 'png'), $name, 'image/png', ownerId: $ownerId);
    }

    private static function createImage(int $width, int $height, string $format): string
    {
        // A red to blue gradient drawn in memory: the ImageMagick policy of the API
        // images refuses the pseudo-image coders ("gradient:", "xc:"…)
        $image = new \Imagick();
        $image->newImage($width, $height, new \ImagickPixel('red'));
        $image->sparseColorImage(\Imagick::SPARSECOLORMETHOD_BARYCENTRIC, [0, 0, 1, 0, 0, 0, max($height - 1, 1), 0, 0, 1]);
        $image->setImageFormat($format);
        $blob = $image->getImageBlob();
        $image->clear();

        return $blob;
    }

    /**
     * @return array{0: int, 1: int} width, height of the stored file
     */
    private static function storedImageSize(File $file): array
    {
        $content = self::storedContent($file);
        $size = getimagesizefromstring($content);
        self::assertIsArray($size, 'the stored file is an image');

        return [$size[0], $size[1]];
    }

    private static function storedContent(File $file): string
    {
        $stream = self::getService(FileStorageManager::class)->getStream($file->getPath());
        try {
            return stream_get_contents($stream);
        } finally {
            fclose($stream);
        }
    }

    private static function isStored(string $path): bool
    {
        return self::getService(FileStorageManager::class)->has($path);
    }

    /**
     * Keeps the p2 and heavy messages (ProcessImageSet, DeleteFilesIfOrphan…) in memory instead of handling them right away.
     * The client must not reboot the kernel (disableReboot()), which would drop the interception.
     */
    private static function interceptImageMessages(): InterceptedTransports
    {
        return InterceptedTransports::intercept(self::getContainer(), 'p2', 'heavy');
    }

    private static function stopInterceptingImageMessages(): void
    {
        InterceptedTransports::stop(self::getContainer(), 'p2', 'heavy');
    }

    /**
     * @return ProcessImageSet[]
     */
    private static function sentProcessImageSetMessages(InterceptedTransports $transport): array
    {
        return array_values(array_filter(
            array_map(static fn ($envelope): object => $envelope->getMessage(), $transport->getSent()),
            static fn (object $message): bool => $message instanceof ProcessImageSet,
        ));
    }
}
