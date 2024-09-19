<?php

namespace Alchemy\RenditionFactory\Transformer\Video;

use Alchemy\RenditionFactory\Context\TransformationContext;
use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use FFMpeg;
use FFMpeg\Filters\Video\VideoFilters;

final readonly class FFMpegTransformerModule implements TransformerModuleInterface
{
    public static function getName(): string
    {
        return 'ffmpeg';
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        $ffmpeg = FFMpeg\FFMpeg::create(); // (new FFMpeg\FFMpeg)->open('/path/to/video');

        if(!($format = $options['format'])) {
            throw new \InvalidArgumentException('Missing format');
        }
        if(!($extension = $options['extension'])) {
            throw new \InvalidArgumentException('Missing extension');
        }
        $format = "FFMpeg\\Format\\Video\\" . $format;
        if(!class_exists($format)) {
            throw new \InvalidArgumentException('Invalid format');
        }
        /** @var FFMpeg\Format\VideoInterface $ouputFormat */
        $outputFormat = new $format();

        $video = $ffmpeg->open( $inputFile->getPath());
        $videoFiltered = $video->filters();
        foreach($options['filters']??[] as $filter) {
            if(!method_exists($this, $filter['name'])) {
                throw new \InvalidArgumentException(sprintf('Invalid filter: %s', $filter['name']));
            }
            $context->log(sprintf('Applying filter: %s', $filter['name']));

            /** @uses self::resize(), self::rotate(), self::pad(), self::crop(), self::clip(), self::synchronize()
             *  @uses self::watermark(), self::framerate()
             */
            $videoFiltered = call_user_func([$this, $filter['name']], $videoFiltered, $filter, $context);
        }

        $outputPath = $context->createTmpFilePath($extension);

        $video->save($outputFormat, $outputPath);

        unset($video);

        return new OutputFile(
            $outputPath,
            'application/octet-stream',
            FamilyEnum::Unknown
        );
    }

    private function resize(VideoFilters $video, array $options, TransformationContextInterface $context): VideoFilters {
        $dimension = $this->getDimension($options, 'resize');
        $mode = $options['mode'] ?? FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_INSET;
        if(!in_array(
            $mode,
            [
                FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_INSET,
                FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_FIT,
                FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_SCALE_WIDTH,
                FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_SCALE_HEIGHT
            ]
        )) {
            throw new \InvalidArgumentException('Invalid mode for filter "resize"');
        }

        return $video->resize(
            $dimension,
            $mode
        );
    }

    private function rotate(VideoFilters $video, array $options, TransformationContextInterface $context): VideoFilters {
        static $rotations = [
            90 => FFMpeg\Filters\Video\RotateFilter::ROTATE_90,
            180 => FFMpeg\Filters\Video\RotateFilter::ROTATE_180,
            270 => FFMpeg\Filters\Video\RotateFilter::ROTATE_270
        ];
        $angle = $options['angle']??0;
        if(!array_key_exists($angle, $rotations)) {
            throw new \InvalidArgumentException('Invalid rotation, must be 90, 180 or 270 for filter "rotate"');
        }

        return $video->rotate($rotations[$angle]);
    }

    private function pad(VideoFilters $video, array $options, TransformationContextInterface $context)
    {
        $dimension = $this->getDimension($options, 'pad');

        return $video->pad($dimension);
    }

    private function crop(VideoFilters $video, array $options, TransformationContextInterface $context)
    {
        $point = new FFMpeg\Coordinate\Point($options['x'] ?? 0, $options['y'] ?? 0);
        $dimension = $this->getDimension($options, 'crop');

        return $video->crop($point, $dimension);
    }

    private function clip(VideoFilters $video, array $options, TransformationContextInterface $context)
    {
        $start = $options['start'] ?? 0;
        $duration = $options['duration'] ?? null;

        $startAsTimecode = $durationAsTimecode = false;
        if(is_string($start)) {
            $startAsTimecode = FFMpeg\Coordinate\TimeCode::fromString($start);
        }
        elseif(is_int($start) && $start >= 0) {
            $startAsTimecode = FFMpeg\Coordinate\TimeCode::fromSeconds($start);
        }
        if($startAsTimecode === false) {
            throw new \InvalidArgumentException('Invalid start for filter "clip"');
        }

        if(is_string($duration)) {
            $durationAsTimecode = FFMpeg\Coordinate\TimeCode::fromString($duration);
        }
        elseif(is_int($duration) && $duration > 0) {
            $durationAsTimecode = FFMpeg\Coordinate\TimeCode::fromSeconds($duration);
        }
        if($durationAsTimecode === false) {
            throw new \InvalidArgumentException('Invalid duration for filter "clip"');
        }

        return $video->clip($startAsTimecode, $durationAsTimecode);
    }

    private function synchronize(VideoFilters $video, array $options, TransformationContextInterface $context)
    {
        return $video->synchronize();
    }

    private function watermark(VideoFilters $video, array $options, TransformationContextInterface $context)
    {
        $path = $options['path']??null;
        if(!file_exists($path)) {
            throw new \InvalidArgumentException('Watermark file for filter "watermark" not found');
        }
        $position = $options['position']??'absolute';
        if($position == 'relative') {
            $coord = array_filter($options, fn($k) => in_array($k, ['bottom', 'right', 'top', 'left']), ARRAY_FILTER_USE_KEY);
            if(array_key_exists('bottom', $coord) && array_key_exists('top', $coord)
                || array_key_exists('right', $coord) && array_key_exists('left', $coord)) {
                throw new \InvalidArgumentException('Invalid relative coordinates for filter "watermark", only one of top/bottom or left/right can be set');
            }
            // in wm filter, missing coord are set to 0
        }
        elseif($position == 'absolute') {
            $coord = array_filter($options, fn($k) => in_array($k, ['x', 'y']), ARRAY_FILTER_USE_KEY);
        }
        else {
            throw new \InvalidArgumentException('Invalid position for filter "watermark"');
        }

        return $video->watermark($path, $coord);
    }

    private function framerate(VideoFilters $video, array $options, TransformationContextInterface $context): VideoFilters
    {
        $framerate = $options['framerate']??0;
        if($framerate <= 0) {
            throw new \InvalidArgumentException('Invalid framerate for filter "framerate"');
        }
        $gop = $options['gop']??0;

        return $video->framerate(new FFMpeg\Coordinate\FrameRate($framerate), $gop);
    }

    private function getDimension(array $options, string $filterName): FFMpeg\Coordinate\Dimension
    {
        $width = $options['width'] ?? 0;
        $height = $options['height'] ?? 0;
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException(sprintf('Invalid width/height for filter "%s"', $filterName));
        }
        return new FFMpeg\Coordinate\Dimension($width, $height);
    }
}
