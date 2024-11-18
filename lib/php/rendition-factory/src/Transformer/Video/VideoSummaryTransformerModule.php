<?php

namespace Alchemy\RenditionFactory\Transformer\Video;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use FFMpeg;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Format\VideoInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final readonly class VideoSummaryTransformerModule extends AbstractVideoTransformer implements TransformerModuleInterface
{
    public static function getName(): string
    {
        return 'video_summary';
    }

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
                    ->scalarNode('period')
                        ->isRequired()
                        ->info('Extract one video clip every period, in seconds or timecode')
                        ->example('5 ; "00:00:05.00"')
                        ->end()
                    ->scalarNode('duration')
                        ->isRequired()
                        ->info('Duration of each clip, in seconds or timecode')
                        ->example('0.25 ; "00:00:00.25"')
                        ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        $context->log("Applying '".self::getName()."' module");

        if (FamilyEnum::Video !== $inputFile->getFamily()) {
            throw new \InvalidArgumentException('Invalid input file family, should be video');
        }

        $commonArgs = new ModuleCommonArgs($this->formats, $options, $context, $this->optionsResolver);
        $outputFormat = $commonArgs->getOutputFormat();
        $format = $outputFormat->getFormat();

        /** @var FFMpeg\Media\Video $video */
        $video = $commonArgs->getFFMpeg()->open($inputFile->getPath());

        $resolverContext = $context->getTemplatingContext();
        $resolverContext['input'] = $video->getStreams()->videos()->first()->all();
        
        $period = $this->optionsResolver->resolveOption($options['period'] ?? 0, $resolverContext);
        $periodAsTimecode = FFMpegHelper::optionAsTimecode($period);
        if (null === $periodAsTimecode || ($period = FFMpegHelper::timecodeToseconds($periodAsTimecode)) <= 0) {
            throw new \InvalidArgumentException(sprintf('Invalid period for module "%s"', self::getName()));
        }

        $start = $this->optionsResolver->resolveOption($options['start'] ?? 0, $resolverContext);
        $startAsTimecode = FFMpegHelper::optionAsTimecode($start);
        if (null === $startAsTimecode || ($start = FFMpegHelper::timecodeToseconds($startAsTimecode)) < 0) {
            throw new \InvalidArgumentException('Invalid start');
        }

        $clipDuration = $this->optionsResolver->resolveOption($options['duration'] ?? 0, $resolverContext);
        $clipDurationAsTimecode = FFMpegHelper::optionAsTimecode($clipDuration);
        if (null === $clipDurationAsTimecode || ($clipDuration = FFMpegHelper::timecodeToseconds($clipDurationAsTimecode)) <= 0 || $clipDuration >= $period) {
            throw new \InvalidArgumentException('Invalid duration, should be >0 and <period');
        }

        $context->log(sprintf('  start=%s (%.02f), period=%s (%.02f), duration=%s (%.02f)', $startAsTimecode, $start, $periodAsTimecode, $period, $clipDurationAsTimecode, $clipDuration));

        $inputDuration = $video->getFFProbe()->format($inputFile->getPath())->get('duration');

        if (FamilyEnum::Video === $outputFormat->getFamily()) {
            /** @var VideoInterface $FFMpegOutputFormat */
            $FFMpegOutputFormat = $outputFormat->getFFMpegFormat();
            if ($videoCodec = $this->optionsResolver->resolveOption($options['video_codec'] ?? null, $resolverContext)) {
                if (!in_array($videoCodec, $FFMpegOutputFormat->getAvailableVideoCodecs())) {
                    throw new \InvalidArgumentException(sprintf('Invalid video codec %s for format %s', $videoCodec, $format));
                }
                $FFMpegOutputFormat->setVideoCodec($videoCodec);
            }
            if ($audioCodec = $this->optionsResolver->resolveOption($options['audio_codec'] ?? null, $resolverContext)) {
                if (!in_array($audioCodec, $FFMpegOutputFormat->getAvailableAudioCodecs())) {
                    throw new \InvalidArgumentException(sprintf('Invalid audio codec %s for format %s', $audioCodec, $format));
                }
                $FFMpegOutputFormat->setAudioCodec($audioCodec);
            }

            // todo: allow to choose other extension
            $clipsExtension = $outputFormat->getAllowedExtensions()[0];

            try {
                $clipsFiles = [];
                $gap = $period - $clipDuration;
                $usableInputDuration = ($inputDuration - $start) + $gap;
                $nClips = floor($usableInputDuration / $period);

                $context->log(sprintf('  Video duration=%.02f, extracting %d clips of %.02f seconds from %s', $inputDuration, $nClips, $clipDuration, $startAsTimecode));
                $removeAudioFilter = new FFMpeg\Filters\Audio\SimpleFilter(['-an']);
                for ($i = 0; $i < $nClips; ++$i) {
                    $startAsTimecode = TimeCode::fromSeconds($start);
                    $context->log(sprintf('  - Extracting clip %d/%d, start=%s (%.02f)', $i + 1, $nClips, $startAsTimecode, $start));
                    $clip = $video->clip($startAsTimecode, $clipDurationAsTimecode);
                    $clip->addFilter($removeAudioFilter);
                    $clipPath = $context->createTmpFilePath($clipsExtension);
                    $clip->save($FFMpegOutputFormat, $clipPath);
                    unset($clip);
                    $clipsFiles[] = realpath($clipPath);
                    $start += $period;
                }
                unset($removeAudioFilter, $video);

                $outVideo = $commonArgs->getFFMpeg()->open($clipsFiles[0]);

                $outputPath = $context->createTmpFilePath($commonArgs->getExtension());

                $outVideo
                    ->concat($clipsFiles)
                    ->saveFromSameCodecs($outputPath, true);

                unset($outVideo);
            } finally {
                foreach ($clipsFiles as $clipFile) {
                    @unlink($clipFile);
                }
            }
        } elseif (FamilyEnum::Animation === $outputFormat->getFamily()) {
            // todo: allow to choose other extension
            $clipsExtension = $outputFormat->getAllowedExtensions()[0];
            try {
                $clipsFiles = [];
                $usableInputDuration = ($inputDuration - $start);
                $nClips = floor($usableInputDuration / $period);

                $context->log(sprintf('  Video duration=%.02f, extracting %d frames from %s', $inputDuration, $nClips, $startAsTimecode));

                for ($i = 0; $i < $nClips; ++$i) {
                    $startAsTimecode = TimeCode::fromSeconds($start);
                    $context->log(sprintf('  - Extracting frame %d/%d, start=%s (%.02f)', $i + 1, $nClips, $startAsTimecode, $start));

                    $frame = $video->frame($startAsTimecode);
                    $clipPath = $context->createTmpFilePath($clipsExtension);
                    $frame->save($clipPath);
                    unset($clip);
                    $clipsFiles[] = realpath($clipPath);

                    $start += $period;
                }
                unset($video);

                $image = $this->imagine->open(array_shift($clipsFiles));
                foreach ($clipsFiles as $file) {
                    $image->layers()->add($this->imagine->open($file));
                }

                $outputPath = $context->createTmpFilePath($commonArgs->getExtension());
                $delay = (int) ($clipDuration * 1000);
                $image->save($outputPath, [
                    'animated' => true,
                    'animated.delay' => $delay,
                    'animated.loops' => 0,
                ]);

            } finally {
                foreach ($clipsFiles as $clipFile) {
                    @unlink($clipFile);
                }
            }
        }

        gc_collect_cycles();

        if (!file_exists($outputPath)) {
            throw new \RuntimeException('Failed to create summary video');
        }

        return new OutputFile(
            $outputPath,
            $outputFormat->getMimeType(),
            $outputFormat->getFamily(),
            false,
        );
    }
}
