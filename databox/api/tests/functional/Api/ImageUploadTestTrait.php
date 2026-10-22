<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Alchemy\MessengerBundle\Transport\TestTransport;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Consumer\Handler\Image\ProcessImageSet;
use App\Entity\Core\File;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Uploads of images (entity list value photos, workspace logos) and their processing.
 */
trait ImageUploadTestTrait
{
    /**
     * Simulates a finished S3 multipart upload (see WorkspaceTermsTest).
     */
    private function createUpload(string $content, string $name, string $type, ?int $size = null): MultipartUpload
    {
        $em = self::getEntityManager();

        $upload = new MultipartUpload();
        $upload->setFilename($name);
        $upload->setType($type);
        $upload->setSize($size ?? strlen($content));
        $upload->setPath(sprintf('test-uploads/%s.%s', uniqid(), pathinfo($name, PATHINFO_EXTENSION)));
        $upload->setUploadId('test-'.uniqid());
        $upload->setComplete(true);
        $em->persist($upload);
        $em->flush();

        self::getService(FileStorageManager::class)->store($upload->getPath(), $content);

        return $upload;
    }

    private static function multipartPayload(MultipartUpload $upload): array
    {
        return [
            'uploadId' => $upload->getId(),
            'parts' => [
                ['ETag' => 'test-etag', 'PartNumber' => 1],
            ],
        ];
    }

    private function createPngUpload(int $width = 1, int $height = 1, string $name = 'image.png'): MultipartUpload
    {
        return $this->createUpload(self::createImage($width, $height, 'png'), $name, 'image/png');
    }

    private static function createImage(int $width, int $height, string $format): string
    {
        $image = new \Imagick();
        $image->newPseudoImage($width, $height, 'gradient:red-blue');
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
     * Keeps the p2 messages (ProcessImageSet) in memory instead of handling them right away.
     * The client must not reboot the kernel (disableReboot()), which would drop the interception.
     */
    private static function interceptP2(): InMemoryTransport
    {
        /** @var TestTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.p2');

        return $transport->intercept();
    }

    private static function stopInterceptingP2(): void
    {
        /** @var TestTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.p2');
        $transport->intercept(false);
    }

    /**
     * @return ProcessImageSet[]
     */
    private static function sentProcessImageSetMessages(InMemoryTransport $transport): array
    {
        return array_values(array_filter(
            array_map(static fn ($envelope): object => $envelope->getMessage(), $transport->getSent()),
            static fn (object $message): bool => $message instanceof ProcessImageSet,
        ));
    }
}
