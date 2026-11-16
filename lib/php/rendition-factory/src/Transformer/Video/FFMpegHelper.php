<?php

namespace Alchemy\RenditionFactory\Transformer\Video;

use Alchemy\BinaryDriver\AbstractBinary;
use Alchemy\RenditionFactory\Transformer\Video\FFMpeg\RestrictedProcessBuilderFactory;
use FFMpeg;
use FFMpeg\Coordinate\TimeCode;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;

class FFMpegHelper
{
    public const int DEFAULT_MAX_TIMEOUT = 3600;

    /**
     * @param int $maxTimeout the highest timeout (seconds) the options may ask for, and the default one:
     *                        the rendition definitions are written by editors, the workers are shared
     */
    public static function createFFMpeg(array $options, int $maxTimeout = self::DEFAULT_MAX_TIMEOUT): FFMpeg\FFMpeg
    {
        $ffmpegOptions = [];
        $ffmpegOptions['timeout'] = self::resolveTimeout($options['timeout'] ?? null, $maxTimeout);

        if ($threads = $options['threads']) {
            if (!is_int($threads) || $threads < 1) {
                throw new \InvalidArgumentException('Invalid threads count');
            }
            $ffmpegOptions['ffmpeg.threads'] = $threads;
        }

        return self::restrict(FFMpeg\FFMpeg::create($ffmpegOptions, $options['logger'] ?? null));
    }

    /**
     * The "timeout" option of the modules running ffmpeg.
     */
    public static function createTimeoutNode(int $maxTimeout): ScalarNodeDefinition
    {
        $node = new ScalarNodeDefinition('timeout');
        $node
            ->info(sprintf('Timeout of each ffmpeg command, in seconds (at most and by default: %d)', $maxTimeout))
            ->validate()
                // A template is only known at build time
                ->ifTrue(static fn (mixed $value): bool => !is_string($value) || !str_contains($value, '{'))
                ->then(static function (mixed $value) use ($maxTimeout): mixed {
                    self::resolveTimeout($value, $maxTimeout);

                    return $value;
                })
            ->end()
        ;

        return $node;
    }

    public static function resolveTimeout(mixed $timeout, int $maxTimeout): int
    {
        if (null === $timeout) {
            return $maxTimeout;
        }
        if (is_string($timeout) && ctype_digit($timeout)) {
            $timeout = (int) $timeout;
        }
        if (!is_int($timeout) || $timeout < 1 || $timeout > $maxTimeout) {
            throw new \InvalidArgumentException(sprintf('Invalid timeout: an integer between 1 and %d is expected, got %s', $maxTimeout, json_encode($timeout)));
        }

        return $timeout;
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
