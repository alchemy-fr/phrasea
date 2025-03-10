<?php

namespace Alchemy\RenditionFactory\Transformer\Video\FFMpeg\Filter;

use FFMpeg\Coordinate\Dimension;
use FFMpeg\Exception\RuntimeException;
use FFMpeg\Filters\Video\VideoFilterInterface;
use FFMpeg\Format\VideoInterface;
use FFMpeg\Media\Video;

class ResizeFilter implements VideoFilterInterface
{
    /** fits to the dimensions, might introduce anamorphosis */
    public const RESIZEMODE_FIT = 'fit';
    /** resizes the video inside the given dimension, no anamorphosis */
    public const RESIZEMODE_INSET = 'inset';
    /** resizes the video to fit the dimension width, no anamorphosis */
    public const RESIZEMODE_SCALE_WIDTH = 'width';
    /** resizes the video to fit the dimension height, no anamorphosis */
    public const RESIZEMODE_SCALE_HEIGHT = 'height';

    public function __construct(
        private Dimension $dimension,
        private string $mode = self::RESIZEMODE_FIT,
        private bool $forceStandards = true,
        private int $priority = 0)
    {
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getDimension(): Dimension
    {
        return $this->dimension;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function areStandardsForced(): bool
    {
        return $this->forceStandards;
    }

    public function apply(Video $video, VideoInterface $format): array
    {
        $rotation = 0;

        try {
            $command = [
                '-loglevel', 'error',
                '-select_streams', 'v:0',
                '-print_format', 'json',
                '-show_entries', 'stream_side_data=rotation',
                '-i', $video->getPathfile(),
            ];
            $r = json_decode($video->getFFProbe()->getFFProbeDriver()->command($command), true, 16, JSON_THROW_ON_ERROR);
            $rotation = (int) $r['streams'][0]['side_data_list'][0]['rotation'];
        } catch (\Exception $e) {
            // ignore (failed to get orientation)
        }

        $dimensions = null;
        $commands = [];

        foreach ($video->getStreams() as $stream) {
            if ($stream->isVideo()) {
                try {
                    $dimensions = $stream->getDimensions();
                    if (90 === $rotation || -90 === $rotation) {
                        $dimensions = new Dimension($dimensions->getHeight(), $dimensions->getWidth());
                    }
                    break;
                } catch (RuntimeException $e) {
                }
            }
        }

        if (null !== $dimensions) {
            $dimensions = $this->getComputedDimensions($dimensions, $format->getModulus());

            // Using Filter to have ordering
            $commands[] = '-vf';
            $commands[] = '[in]scale='.$dimensions->getWidth().':'.$dimensions->getHeight().' [out]';
        }

        return $commands;
    }

    private function getComputedDimensions(Dimension $dimension, $modulus): Dimension
    {
        $originalRatio = $dimension->getRatio($this->forceStandards);
        switch ($this->mode) {
            case self::RESIZEMODE_SCALE_WIDTH:
                $height = $this->dimension->getHeight();
                $width = $originalRatio->calculateWidth($height, $modulus);
                break;
            case self::RESIZEMODE_SCALE_HEIGHT:
                $width = $this->dimension->getWidth();
                $height = $originalRatio->calculateHeight($width, $modulus);
                break;
            case self::RESIZEMODE_INSET:
                $targetRatio = $this->dimension->getRatio($this->forceStandards);

                if ($targetRatio->getValue() > $originalRatio->getValue()) {
                    $height = $this->dimension->getHeight();
                    $width = $originalRatio->calculateWidth($height, $modulus);
                } else {
                    $width = $this->dimension->getWidth();
                    $height = $originalRatio->calculateHeight($width, $modulus);
                }
                break;
            case self::RESIZEMODE_FIT:
            default:
                $width = $this->dimension->getWidth();
                $height = $this->dimension->getHeight();
                break;
        }

        return new Dimension($width, $height);
    }
}
