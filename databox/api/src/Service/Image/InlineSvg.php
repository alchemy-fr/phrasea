<?php

declare(strict_types=1);

namespace App\Service\Image;

use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use enshrined\svgSanitize\Sanitizer;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * SVG images are kept inline (ImageSet::$svgData) and served as data URIs, so that they
 * come with the API response. Their size is limited since the data URI is repeated in
 * every output carrying the image.
 */
final readonly class InlineSvg
{
    public const string TYPE = 'image/svg+xml';
    public const int MAX_SIZE = 100 * 1024;

    public function __construct(
        private FileStorageManager $fileStorageManager,
    ) {
    }

    /**
     * Reads and sanitizes the uploaded SVG, then deletes the uploaded object.
     */
    public function readUpload(MultipartUpload $upload): string
    {
        if ($upload->getSize() > self::MAX_SIZE) {
            throw self::createTooBigException();
        }

        $stream = $this->fileStorageManager->getStream($upload->getPath());
        try {
            // Read one byte more than allowed to detect a size mismatch with the upload record
            $content = stream_get_contents($stream, self::MAX_SIZE + 1);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (false === $content || strlen($content) > self::MAX_SIZE) {
            throw self::createTooBigException();
        }

        $svg = self::sanitize($content);
        $this->fileStorageManager->delete($upload->getPath());

        return $svg;
    }

    public static function sanitize(string $content): string
    {
        $sanitizer = new Sanitizer();
        $sanitizer->removeRemoteReferences(true);
        $sanitizer->minify(true);

        $svg = $sanitizer->sanitize($content);
        if (!is_string($svg) || '' === trim($svg)) {
            throw new BadRequestHttpException('Invalid SVG image');
        }

        return $svg;
    }

    public static function toDataUri(string $svg): string
    {
        return sprintf('data:%s;base64,%s', self::TYPE, base64_encode($svg));
    }

    private static function createTooBigException(): BadRequestHttpException
    {
        return new BadRequestHttpException(sprintf('SVG image must not exceed %d KB', self::MAX_SIZE / 1024));
    }
}
