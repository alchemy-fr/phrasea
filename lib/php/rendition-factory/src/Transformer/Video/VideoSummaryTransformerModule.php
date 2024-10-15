<?php

namespace Alchemy\RenditionFactory\Transformer\Video;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Alchemy\RenditionFactory\Transformer\Video\FFMpeg\Format\FormatInterface;
use FFMpeg;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Format\VideoInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;


final readonly class VideoSummaryTransformerModule implements TransformerModuleInterface
{
    public function __construct(#[AutowireLocator(FormatInterface::TAG, defaultIndexMethod: 'getFormat')] private ServiceLocator $formats)
    {
    }

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
        if (!($format = $options['format'] ?? null)) {
            throw new \InvalidArgumentException('Missing format');
        }

        if(!$this->formats->has($format)) {
            throw new \InvalidArgumentException(sprintf('Invalid format %s', $format));
        }
        /** @var FormatInterface $outputFormat */
        $outputFormat = $this->formats->get($format);

        if($outputFormat->getFamily() !== FamilyEnum::Video) {
            throw new \InvalidArgumentException(sprintf('Invalid format %s, only video formats supported', $format));
        }

        if (null != ($extension = $options['extension'] ?? null)) {
            if(!in_array($extension, $outputFormat->getAllowedExtensions())) {
                throw new \InvalidArgumentException(sprintf('Invalid extension %s for format %s', $extension, $format));
            }
        }
        else {
            $extension = ($outputFormat->getAllowedExtensions())[0];
        }

        $period = $options['period'] ?? 0;
        if ($period <= 0) {
            throw new \InvalidArgumentException(sprintf('Invalid period for module "%s"', self::getName()));
        }
        $clipDuration = $options['duration'] ?? 0;
        if ($clipDuration <= 0 || $clipDuration >= $period) {
            throw new \InvalidArgumentException(sprintf('Invalid duration for module "%s"', self::getName()));
        }

        /** @var VideoInterface $FFMpegOutputFormat */
        $FFMpegOutputFormat = $outputFormat->getFFMpegFormat();
        if ($videoCodec = $options['video_codec'] ?? null) {
            if (!in_array($videoCodec, $FFMpegOutputFormat->getAvailableVideoCodecs())) {
                throw new \InvalidArgumentException(sprintf('Invalid video codec %s for format %s', $videoCodec, $format));
            }
            $FFMpegOutputFormat->setVideoCodec($videoCodec);
        }
        if ($audioCodec = $options['audio_codec'] ?? null) {
            if (!in_array($audioCodec, $FFMpegOutputFormat->getAvailableAudioCodecs())) {
                throw new \InvalidArgumentException(sprintf('Invalid audio codec %s for format %s', $audioCodec, $format));
            }
            $FFMpegOutputFormat->setAudioCodec($audioCodec);
        }

        $clipsExtension = ($outputFormat->getAllowedExtensions())[0];

        $clipsFiles = [];
        try {
            $ffmpeg = FFMpeg\FFMpeg::create([], $context->getLogger());
            /** @var FFMpeg\Media\Video $video */
            $video = $ffmpeg->open($inputFile->getPath());

            $inputDuration = $video->getFFProbe()->format($inputFile->getPath())->get('duration');
            $nClips = ceil($inputDuration / $period);

            $context->log(sprintf('Duration duration: %s, extracting %d clips of %d seconds', $inputDuration, $nClips, $clipDuration));
            $clipDuration = TimeCode::fromSeconds($clipDuration);
            $removeAudioFilter = new FFMpeg\Filters\Audio\SimpleFilter(['-an']);
            for ($i = 0; $i < $nClips; ++$i) {
                $start = $i * $period;
                $clip = $video->clip(TimeCode::fromSeconds($start), $clipDuration);
                $clip->addFilter($removeAudioFilter);
                $clipPath = $context->createTmpFilePath($clipsExtension);
                $clip->save($FFMpegOutputFormat, $clipPath);
                unset($clip);
                gc_collect_cycles();
                $clipsFiles[] = realpath($clipPath);
            }
            unset($removeAudioFilter, $video);
            gc_collect_cycles();

            $outVideo = $ffmpeg->open($clipsFiles[0]);

            $outputPath = $context->createTmpFilePath($extension);

            $outVideo
                ->concat($clipsFiles)
                ->saveFromSameCodecs($outputPath, true);

            unset($outVideo, $ffmpeg);
            gc_collect_cycles();
        } finally {
            foreach ($clipsFiles as $clipFile) {
                @unlink($clipFile);
            }
        }

        if (!file_exists($outputPath)) {
            throw new \RuntimeException(sprintf('Failed to create summary video'));
        }

        return new OutputFile(
            $outputPath,
            $outputFormat->getMimeType(),
            $outputFormat->getFamily(),
        );
    }
}
