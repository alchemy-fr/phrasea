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

final readonly class VideoSummaryTransformerModule extends AbstractVideoTransformer implements TransformerModuleInterface
{
    public static function getName(): string
    {
        return 'video_summary';
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

        $resolverContext = [
            'metadata' => $context->getTemplatingContext(),
            'input' => $video->getStreams()->videos()->first()->all(),
        ];

        $period = $this->optionsResolver->resolveOption($options['period'] ?? 0, $resolverContext);
        if ($period <= 0) {
            throw new \InvalidArgumentException(sprintf('Invalid period for module "%s"', self::getName()));
        }
        $clipDuration = $this->optionsResolver->resolveOption($options['duration'] ?? 0, $resolverContext);
        if ($clipDuration <= 0 || $clipDuration >= $period) {
            throw new \InvalidArgumentException(sprintf('Invalid duration for module "%s"', self::getName()));
        }

        $context->log(sprintf('  period: %d, duration: %d', $period, $clipDuration));

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

        $clipsExtension = $outputFormat->getAllowedExtensions()[0];

        $clipsFiles = [];
        try {
            $inputDuration = $video->getFFProbe()->format($inputFile->getPath())->get('duration');
            $nClips = ceil($inputDuration / $period);

            $context->log(sprintf('  Duration duration: %s, extracting %d clips of %d seconds', $inputDuration, $nClips, $clipDuration));
            $clipDuration = TimeCode::fromSeconds($clipDuration);
            $removeAudioFilter = new FFMpeg\Filters\Audio\SimpleFilter(['-an']);
            for ($i = 0; $i < $nClips; ++$i) {
                $start = $i * $period;
                $clip = $video->clip(TimeCode::fromSeconds($start), $clipDuration);
                $clip->addFilter($removeAudioFilter);
                $clipPath = $context->createTmpFilePath($clipsExtension);
                $clip->save($FFMpegOutputFormat, $clipPath);
                unset($clip);
                $clipsFiles[] = realpath($clipPath);
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
