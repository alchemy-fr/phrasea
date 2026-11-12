<?php

namespace Alchemy\RenditionFactory\Transformer\Video;

use Alchemy\BinaryDriver\AbstractBinary;
use Alchemy\RenditionFactory\Transformer\Video\FFMpeg\RestrictedProcessBuilderFactory;
use FFMpeg;
use FFMpeg\Coordinate\TimeCode;

class FFMpegHelper
{
    public static function createFFMpeg(array $options): FFMpeg\FFMpeg
    {
        $ffmpegOptions = [];

        if (!is_int($timeout = $options['timeout'] ?? 3600) || $timeout < 1) {
            throw new \InvalidArgumentException('Invalid timeout');
        }
        $ffmpegOptions['timeout'] = $timeout;

        if ($threads = $options['threads']) {
            if (!is_int($threads) || $threads < 1) {
                throw new \InvalidArgumentException('Invalid threads count');
            }
            $ffmpegOptions['ffmpeg.threads'] = $threads;
        }

        return self::restrict(FFMpeg\FFMpeg::create($ffmpegOptions, $options['logger'] ?? null));
    }

    /**
     * Limits ffmpeg and its ffprobe to local files (see RestrictedProcessBuilderFactory).
     * To call before opening any media: a media keeps the drivers it was opened with.
     */
    public static function restrict(FFMpeg\FFMpeg $ffmpeg): FFMpeg\FFMpeg
    {
        self::restrictDriver($ffmpeg->getFFMpegDriver(), false);
        self::restrictProbe($ffmpeg->getFFProbe());

        return $ffmpeg;
    }

    public static function restrictProbe(FFMpeg\FFProbe $probe): FFMpeg\FFProbe
    {
        self::restrictDriver($probe->getFFProbeDriver(), true);

        return $probe;
    }

    private static function restrictDriver(AbstractBinary $driver, bool $probe): void
    {
        $factory = $driver->getProcessBuilderFactory();
        if ($factory instanceof RestrictedProcessBuilderFactory) {
            return;
        }

        $restricted = new RestrictedProcessBuilderFactory($factory->getBinary(), $probe);
        if (null !== $timeout = $factory->getTimeout()) {
            $restricted->setTimeout($timeout);
        }
        $driver->setProcessBuilderFactory($restricted);
    }

    public static function pointAsText(FFMpeg\Coordinate\Point $point): string
    {
        return sprintf('(%d, %d)', $point->getX(), $point->getY());
    }

    public static function dimensionAsText(FFMpeg\Coordinate\Dimension $dimension): string
    {
        return sprintf('%d x %d', $dimension->getWidth(), $dimension->getHeight());
    }

    public static function coordAsText(array $coord): string
    {
        $s = [];
        foreach ($coord as $k => $v) {
            $s[] = sprintf('%s=%s', $k, $v);
        }

        return '['.implode(', ', $s).']';
    }

    public static function optionAsTimecode($value): ?TimeCode
    {
        if (is_numeric($value) && $value >= 0.0) {
            return TimeCode::fromSeconds($value);
        } elseif (is_string($value)) {
            return TimeCode::fromString($value);
        }

        return null;
    }

    public static function timecodeToseconds(TimeCode $timecode): float
    {
        if (preg_match('/^[0-9]+:[0-9]+:[0-9]+\.[0-9]+$/', (string) $timecode)) {
            [$hours, $minutes, $seconds, $frames] = sscanf($timecode, '%d:%d:%d.%d');
        }
        $s = 0.0;

        $s += $hours * 60 * 60;
        $s += $minutes * 60;
        $s += $seconds;
        $s += $frames / 100;

        return $s;
    }
}
