<?php

namespace Alchemy\RenditionFactory\Transformer\Video;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use FFMpeg;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final readonly class VideoToAnimationTransformerModule extends AbstractVideoTransformer implements TransformerModuleInterface
{
    public static function getName(): string
    {
        return 'video_to_animation';
    }

    public static function getDocumentationHeader(): ?string
    {
        return 'Converts a video to an animated gif';
    }

    //    public static function getDocumentationFooter(): ?string
    //    {
    //        return null;
    //    }
    //
    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('module')
                ->isRequired()
                ->defaultValue(self::getName())
                ->end()
            ->booleanNode('enabled')
                ->defaultTrue()
                ->info('Whether to enable this module')
                ->end()
            ->arrayNode('options')
                ->info('Options for the module')
                ->children()
                    ->scalarNode('start')
                        ->defaultValue(0)
                        ->info('Start time in seconds or timecode')
                        ->example('2.5 ; "00:00:02.50" ; "{{ metadata.start }}"')
                        ->end()
                    ->scalarNode('duration')
                        ->defaultValue(null)
                        ->info('Duration in seconds or timecode')
                        ->example('30 ; "00:00:30.00" ; "{{ input.duration/2 }}"')
                        ->end()
                    ->integerNode('fps')
                        ->defaultValue(1)
                        ->info('Frames per second')
                        ->end()
                    ->integerNode('width')
                        ->defaultValue(null)
                        ->info('Width in pixels')
                        ->end()
                    ->integerNode('height')
                        ->defaultValue(null)
                        ->info('Height in pixels')
                        ->end()
                    ->enumNode('mode')
                        ->values([
                            FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_INSET,
                            // todo: implement other modes
                            // FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_FIT,
                            // FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_SCALE_WIDTH,
                            // FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_SCALE_HEIGHT,
                        ])
                        ->defaultValue(FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_INSET)
                        ->info('Resize mode')
                        ->end()
                ->end()
            ->end()
        ;
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        $context->log("Applying '".self::getName()."' module");

        if (FamilyEnum::Video !== $inputFile->getFamily()) {
            throw new \InvalidArgumentException('Invalid input file family, should be video');
        }

        $commonArgs = new ModuleCommonArgs($this->formats, $options, $context, $this->optionsResolver);
        $outputFormat = $commonArgs->getOutputFormat();

        /** @var FFMpeg\Media\Video $video */
        $video = $commonArgs->getFFMpeg()->open($inputFile->getPath());

        $resolverContext = [
            'metadata' => $context->getTemplatingContext(),
            'input' => $video->getStreams()->videos()->first()->all(),
        ];

        $start = $this->optionsResolver->resolveOption($options['start'] ?? 0, $resolverContext);
        $startAsTimecode = FFMpegHelper::optionAsTimecode($start);
        if (null === $startAsTimecode) {
            throw new \InvalidArgumentException('Invalid start.');
        }
        $start = FFMpegHelper::timecodeToseconds($startAsTimecode);

        $duration = $this->optionsResolver->resolveOption($options['duration'] ?? null, $resolverContext);
        if (null !== $duration) {
            $durationAsTimecode = FFMpegHelper::optionAsTimecode($duration);
            if (null === $durationAsTimecode) {
                throw new \InvalidArgumentException('Invalid duration for filter "clip"');
            }
            $duration = FFMpegHelper::timecodeToseconds($durationAsTimecode);
        }

        if (($fps = (int) $this->optionsResolver->resolveOption($options['fps'] ?? 1, $resolverContext)) <= 0) {
            throw new \InvalidArgumentException('Invalid fps');
        }

        $width = $this->optionsResolver->resolveOption($options['width'] ?? null, $resolverContext);
        $height = $this->optionsResolver->resolveOption($options['height'] ?? null, $resolverContext);
        if ((null !== $width && ($width = (int) $width) <= 0) || (null !== $height && ($height = (int) $height) <= 0)) {
            throw new \InvalidArgumentException('Invalid width or height');
        }

        $mode = $this->optionsResolver->resolveOption($options['mode'] ?? FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_INSET, $resolverContext);
        if (!in_array(
            $mode,
            [
                FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_INSET,
                FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_FIT,
                FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_SCALE_WIDTH,
                FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_SCALE_HEIGHT,
            ]
        )) {
            throw new \InvalidArgumentException('Invalid resize mode');
        }
        switch ($mode) {
            case FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_INSET:
                [$width, $height] = $this->getDimensionsInset($video, $width, $height);
                break;
                // other modes not implemented
            default:
                throw new \InvalidArgumentException('Invalid resize mode');
        }

        $context->log(sprintf('  start=%s, duration=%s, fps=%s, width=%d, height=%d', $start, $duration, $fps, $width, $height));

        $commands = [
            '-i',
            $inputFile->getPath(),
            '-ss',
            $start,
        ];
        if (null !== $duration) {
            $commands[] = '-t';
            $commands[] = $duration;
        }
        $commands[] = '-vf';

        $c = 'fps='.$fps;
        if (-1 !== $width || -1 !== $height) {
            $c .= ',scale='.$width.':'.$height.':flags=lanczos';
        }
        $c .= ',split[s0][s1];[s0]palettegen[p];[s1][p]paletteuse';
        $commands[] = $c;

        $commands[] = '-loop';
        $commands[] = '0';

        $outputPath = $context->createTmpFilePath($commonArgs->getExtension());
        $commands[] = $outputPath;

        $commonArgs->getFFMpeg()->getFFMpegDriver()->command($commands);

        if (!file_exists($outputPath)) {
            throw new \RuntimeException('Failed to create animated gif');
        }

        return new OutputFile(
            $outputPath,
            $outputFormat->getMimeType(),
            $outputFormat->getFamily(),
            false,
        );
    }

    private function getDimensionsInset(FFMpeg\Media\Video $video, $width, $height): array
    {
        if (null === $width && null === $height) {
            return [-1, -1];
        }
        if (null === $width) {
            return [-1, $height];
        }
        if (null === $height) {
            return [$width, -1];
        }
        $dimensions = null;
        foreach ($video->getStreams() as $stream) {
            if ($stream->isVideo()) {
                try {
                    $dimensions = $stream->getDimensions();
                    break;
                } catch (\Exception $e) {
                    // no-op
                }
            }
        }
        if ($dimensions) {
            $wRatio = $width ? ($dimensions->getWidth() / $width) : 0;
            $hRatio = $height ? ($dimensions->getHeight() / $height) : 0;
            if ($wRatio > $hRatio) {
                return [(int) floor($dimensions->getWidth() / $wRatio), -1];
            } else {
                return [-1, (int) floor($dimensions->getHeight() / $hRatio)];
            }
        }

        // fallback : exact fit (might be not homothetic)
        return [$width, $height];
    }
}
