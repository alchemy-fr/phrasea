<?php

declare(strict_types=1);

namespace App\Integration\Core\FaceRecognition;

use App\Entity\Core\Attribute;
use App\Integration\IntegrationConfig;
use App\Service\Asset\FileFetcher;
use App\Service\Image\ImageManagerFactory;

/**
 * Crops a face out of the image it was detected on, as the photo of its person.
 *
 * The framing is the one of the client (databox/client/src/features/faces/FaceCrop.tsx): a square
 * centered on the box, whose side is CONTEXT times the longest side of the box.
 */
final readonly class FacePhotoCropper
{
    /**
     * Image area around the face, relative to its longest side.
     */
    final public const float CONTEXT = 1.8;

    private const int QUALITY = 90;

    public function __construct(
        private FaceRecognitionAnalyzer $analyzer,
        private FileFetcher $fileFetcher,
        private ImageManagerFactory $imageManagerFactory,
    ) {
    }

    /**
     * Returns the path of a temporary JPEG of the face (to be deleted by the caller), or null when
     * the face has no box or its asset no image.
     *
     * @throws \App\Service\Asset\Exception\PrivateFileException  when the image cannot be fetched
     * @throws \Intervention\Image\Exception\NotReadableException when the image cannot be decoded
     */
    public function crop(Attribute $face, IntegrationConfig $config): ?string
    {
        $box = FaceRecognitionAnalyzer::boxOf($face);
        if (null === $box) {
            return null;
        }
        // The boxes are relative to the file the detection ran on
        $file = $this->analyzer->resolveFile($face->getAsset(), $config);
        if (null === $file) {
            return null;
        }

        $alreadyFetched = null !== $file->localTmpPath && file_exists($file->localTmpPath);
        $src = $this->fileFetcher->getFile($file);
        try {
            $image = $this->imageManagerFactory->createManager()->make($src);
            // The boxes are relative to the oriented image (see ImageDownscaler)
            $image->orientate();

            $square = self::square($box, $image->width(), $image->height());
            $image->crop($square['side'], $square['side'], $square['x'], $square['y']);

            // The pixels are oriented already: an orientation left in the EXIF data would rotate them again
            $core = $image->getCore();
            if ($core instanceof \Imagick) {
                $core->stripImage();
            }

            $tmp = tempnam(sys_get_temp_dir(), 'face-photo-');
            @unlink($tmp);
            $target = $tmp.'.jpg';
            $image->save($target, self::QUALITY, 'jpg');
            $image->destroy();

            return $target;
        } finally {
            if (!$alreadyFetched && file_exists($src)) {
                unlink($src);
                $file->localTmpPath = null;
            }
        }
    }

    /**
     * The square to crop around the box, in pixels: centered on the box, capped at the shorter side
     * of the image, and shifted inside the image at its edges.
     *
     * @param array{x: float, y: float, w: float, h: float} $box relative to the image (0..1)
     *
     * @return array{x: int, y: int, side: int}
     */
    public static function square(array $box, int $width, int $height): array
    {
        $side = (int) round(self::CONTEXT * max($box['w'] * $width, $box['h'] * $height));
        $side = max(1, min($side, $width, $height));

        $centerX = ($box['x'] + $box['w'] / 2) * $width;
        $centerY = ($box['y'] + $box['h'] / 2) * $height;

        return [
            'x' => max(0, min((int) round($centerX - $side / 2), $width - $side)),
            'y' => max(0, min((int) round($centerY - $side / 2), $height - $side)),
            'side' => $side,
        ];
    }
}
