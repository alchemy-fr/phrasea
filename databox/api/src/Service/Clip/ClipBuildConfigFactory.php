<?php

declare(strict_types=1);

namespace App\Service\Clip;

use Alchemy\RenditionFactory\DTO\BuildConfig\BuildConfig;
use Alchemy\RenditionFactory\DTO\BuildConfig\FamilyBuildConfig;
use Alchemy\RenditionFactory\DTO\BuildConfig\Transformation;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\MimeType\ImageFormatGuesser;
use Alchemy\RenditionFactory\Transformer\Image\Imagine\ImagineTransformerModule;
use Alchemy\RenditionFactory\Transformer\Video\FFMpegTransformerModule;
use Alchemy\RenditionFactory\Transformer\Video\Format\AacFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\MkvFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\Mp3Format;
use Alchemy\RenditionFactory\Transformer\Video\Format\Mpeg4Format;
use Alchemy\RenditionFactory\Transformer\Video\Format\OgaFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\OgvFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\QuicktimeFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\WavFormat;
use Alchemy\RenditionFactory\Transformer\Video\Format\WebmFormat;
use App\Model\BasketClip;

/**
 * Builds the rendition build config cutting a file to a basket clip.
 */
final readonly class ClipBuildConfigFactory
{
    public const int IMAGE_QUALITY = 95;
    public const int MIN_VIDEO_KILOBITRATE = 500;
    private const int DEFAULT_VIDEO_KILOBITRATE = 2000;

    /**
     * Formats encoded with x264: a constant quality (CRF, one pass) is used.
     */
    private const array X264_FORMATS = [
        Mpeg4Format::FORMAT,
        QuicktimeFormat::FORMAT,
        MkvFormat::FORMAT,
    ];

    /**
     * @return BuildConfig|null null when nothing has to be done
     */
    public function create(FamilyEnum $family, string $mimeType, ?BasketClip $clip, ?VideoProbe $probe = null): ?BuildConfig
    {
        $clip = $clip?->forFamily($family);
        if (null === $clip) {
            return null;
        }

        return match ($family) {
            FamilyEnum::Video => self::wrap($family, FFMpegTransformerModule::getName(), $this->createVideoOptions($mimeType, $clip, $probe)),
            FamilyEnum::Audio => self::wrap($family, FFMpegTransformerModule::getName(), $this->createAudioOptions($mimeType, $clip)),
            FamilyEnum::Image => self::wrap($family, ImagineTransformerModule::NAME, $this->createImageOptions($mimeType, $clip)),
            default => null,
        };
    }

    private function createVideoOptions(string $mimeType, BasketClip $clip, ?VideoProbe $probe): array
    {
        $filters = [];
        if ($clip->hasTimeRange()) {
            [$start, $duration] = self::getTimeRange($clip, $probe?->duration);
            // pre_clip seeks before decoding: it must come first
            $filters[] = [
                'name' => 'pre_clip',
                'start' => $start,
                'duration' => $duration,
            ];
        }

        if ($clip->hasCrop()) {
            if (null === $probe) {
                throw new \InvalidArgumentException('The video size is required to crop it');
            }
            [$width, $height] = $probe->getDisplaySize();
            [$x, $cropWidth] = self::toEvenPixels($clip->x, $clip->width, $width);
            [$y, $cropHeight] = self::toEvenPixels($clip->y, $clip->height, $height);

            $filters[] = [
                'name' => 'crop',
                'x' => $x,
                'y' => $y,
                'width' => $cropWidth,
                'height' => $cropHeight,
            ];
        }

        [$format, $extension] = self::getVideoFormat($mimeType);
        $options = [
            'format' => $format,
            'extension' => $extension,
            'filters' => $filters,
        ];

        if (in_array($format, self::X264_FORMATS, true)) {
            // A 0 bitrate switches x264 to a constant quality, in a single pass
            $options['video_kilobitrate'] = 0;
        } else {
            $options['video_kilobitrate'] = max(self::MIN_VIDEO_KILOBITRATE, $probe?->kbps ?? self::DEFAULT_VIDEO_KILOBITRATE);
        }

        return $options;
    }

    private function createAudioOptions(string $mimeType, BasketClip $clip): array
    {
        [$start, $duration] = self::getTimeRange($clip, null);
        [$format, $extension] = self::getAudioFormat($mimeType);

        return [
            'format' => $format,
            'extension' => $extension,
            'filters' => [
                [
                    'name' => 'clip',
                    'start' => $start,
                    'duration' => $duration,
                ],
            ],
        ];
    }

    private function createImageOptions(string $mimeType, BasketClip $clip): array
    {
        $format = ImageFormatGuesser::getFormat($mimeType);
        if (null === $format || 'svg' === $format) {
            $format = 'jpeg';
        }

        return [
            'format' => $format,
            // Not part of the module's configuration tree, but handed to the image export
            'quality' => self::IMAGE_QUALITY,
            'filters' => [
                // The crop was drawn on the displayed (rotated) image
                'auto_rotate' => null,
                'relative_crop' => [
                    'x' => $clip->x,
                    'y' => $clip->y,
                    'width' => $clip->width,
                    'height' => $clip->height,
                ],
            ],
        ];
    }

    /**
     * @return array{string, string} the output format keeping the source container, and its extension
     */
    public static function getVideoFormat(string $mimeType): array
    {
        return match ($mimeType) {
            'video/quicktime' => [QuicktimeFormat::FORMAT, 'mov'],
            'video/webm' => [WebmFormat::FORMAT, 'webm'],
            'video/x-matroska' => [MkvFormat::FORMAT, 'mkv'],
            'video/ogg', 'application/ogg' => [OgvFormat::FORMAT, 'ogv'],
            default => [Mpeg4Format::FORMAT, 'mp4'],
        };
    }

    /**
     * @return array{string, string}
     */
    public static function getAudioFormat(string $mimeType): array
    {
        return match ($mimeType) {
            'audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave' => [WavFormat::FORMAT, 'wav'],
            'audio/aac', 'audio/x-aac' => [AacFormat::FORMAT, 'aac'],
            'audio/mp4', 'audio/x-m4a', 'audio/m4a' => [AacFormat::FORMAT, 'm4a'],
            'audio/ogg', 'audio/vorbis', 'audio/x-vorbis+ogg' => [OgaFormat::FORMAT, 'ogg'],
            default => [Mp3Format::FORMAT, 'mp3'],
        };
    }

    /**
     * @return array{float, float} the start and the duration, bounded to the media duration when known
     */
    private static function getTimeRange(BasketClip $clip, ?float $mediaDuration): array
    {
        $start = max(0.0, $clip->start);
        $end = $clip->end;
        if (null !== $mediaDuration && $mediaDuration > 0) {
            $end = min($end, $mediaDuration);
        }
        if ($end <= $start) {
            throw new \InvalidArgumentException(sprintf('The clip starts at %.2fs, after the end of the media (%.2fs)', $start, $end));
        }

        return [$start, round($end - $start, 3)];
    }

    /**
     * Even values (required by the yuv420p chroma subsampling), at least 2px, within the frame.
     *
     * @return array{int, int} the offset and the length
     */
    private static function toEvenPixels(float $start, float $length, int $total): array
    {
        $max = $total - $total % 2;
        if ($max < 2) {
            throw new \InvalidArgumentException(sprintf('Invalid video size: %dpx', $total));
        }

        $length = (int) round($length * $total);
        $length -= $length % 2;
        $length = max(2, min($max, $length));

        $offset = (int) round($start * $total);
        $offset -= $offset % 2;
        $offset = max(0, min($max - $length, $offset));

        return [$offset, $length];
    }

    private static function wrap(FamilyEnum $family, string $module, array $options): BuildConfig
    {
        return new BuildConfig([
            $family->value => new FamilyBuildConfig([
                new Transformation($module, true, $options, null),
            ], []),
        ], null);
    }
}
