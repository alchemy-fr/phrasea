<?php

namespace Alchemy\RenditionFactory\Transformer\Video;

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

        return FFMpeg\FFMpeg::create($ffmpegOptions, $options['logger'] ?? null);
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
