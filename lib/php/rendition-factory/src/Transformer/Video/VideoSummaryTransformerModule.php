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
use FFMpeg\Format\FormatInterface;
use InvalidArgumentException;

final readonly class VideoSummaryTransformerModule implements TransformerModuleInterface
{
    public static function getName(): string
    {
        return 'video_summary';
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        if (!($format = $options['format'])) {
            throw new InvalidArgumentException('Missing format');
        }
        if (!($extension = $options['extension'])) {
            throw new InvalidArgumentException('Missing extension');
        }

        $fqcnFormat = 'FFMpeg\\Format\\Video\\'.$format;
        if (class_exists($fqcnFormat)) {
            return $this->processVideo($format, $extension, $inputFile, $options, $context);
        }

        throw new InvalidArgumentException(sprintf('Invalid format %s', $format));
    }

    private function processVideo(string $format, string $extension, InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        $period = $options['period'] ?? 0;
        if ($period <= 0) {
            throw new InvalidArgumentException(sprintf('Invalid period for module "%s"', self::getName()));
        }
        $clipDuration = $options['duration'] ?? 0;
        if ($clipDuration <= 0 || $clipDuration >= $period) {
            throw new InvalidArgumentException(sprintf('Invalid duration for module "%s"', self::getName()));
        }

        $fqcnFormat = 'FFMpeg\\Format\\Video\\'.$format;
        $outpuFormat = new $fqcnFormat();
        if ($videoCodec = $options['video_codec'] ?? null) {
            if (!in_array($videoCodec, $outpuFormat->getAvailableVideoCodecs())) {
                throw new InvalidArgumentException(sprintf('Invalid video codec %s for format %s', $videoCodec, $format));
            }
            $outpuFormat->setVideoCodec($videoCodec);
        }
        if ($audioCodec = $options['audio_codec'] ?? null) {
            if (!in_array($audioCodec, $outpuFormat->getAvailableAudioCodecs())) {
                throw new InvalidArgumentException(sprintf('Invalid audio codec %s for format %s', $audioCodec, $format));
            }
            $outpuFormat->setAudioCodec($audioCodec);
        }

        // try to find an "enhanced by alchemy" format (supports "copy" video codec) for clips

        switch ($inputFile->getType()) {
            case 'video/mp4':
            case 'video/mov':
                $clipsFormatName = 'X264';
                break;
            case 'video/webm':
                $clipsFormatName = 'WebM';
                break;
            case 'video/ogg':
                $clipsFormatName = 'Ogg';
                break;
            default:
                $clipsFormatName = '?';
                break;
        }

        /** @var FormatInterface $clipsFormat */
        $fqcnFormat = 'Alchemy\\RenditionFactory\\Transformer\\Video\\FFMpeg\\Format\\Video\\'.$clipsFormatName;
        if (class_exists($fqcnFormat)) {
            $clipsFormat = new $fqcnFormat();
            if (in_array('copy', $clipsFormat->getAvailableVideoCodecs())) {
                $clipsFormat->setVideoCodec('copy');
            }
            if (in_array('copy', $clipsFormat->getAvailableAudioCodecs())) {
                $clipsFormat->setAudioCodec('copy');
            }
            $clipsExtension = $inputFile->getExtension();
        } else {
            $clipsFormat = $outpuFormat;
            $clipsExtension = $extension;
        }

        $outputPath = $context->createTmpFilePath($extension);
        $clipsFiles = [];
        try {
            $ffmpeg = FFMpeg\FFMpeg::create([], $context->getLogger());
            /** @var FFMpeg\Media\Video $video */
            $video = $ffmpeg->open($inputFile->getPath());

            $inputDuration = $video->getFFProbe()->format($inputFile->getPath())->get('duration');
            $nClips = ceil($inputDuration / $period);

            $context->log(sprintf('Duration duration: %s, extracting %d clips of %d seconds', $inputDuration, $nClips, $clipDuration));
            $clipDuration = TimeCode::fromSeconds($clipDuration);
            for ($i = 0; $i < $nClips; ++$i) {
                $start = $i * $period;
                $clip = $video->clip(TimeCode::fromSeconds($start), $clipDuration);
                $clipPath = $context->createTmpFilePath($clipsExtension);
                $clip->save($clipsFormat, $clipPath);
                $clipsFiles[] = realpath($clipPath);
            }

            $outVideo = $ffmpeg->open($clipsFiles[0]);

            if ($format == $clipsFormatName) {
                $outVideo
                    ->concat($clipsFiles)
                    ->saveFromSameCodecs($outputPath, true);
            } else {
                $outVideo
                    ->concat($clipsFiles)
                    ->saveFromDifferentCodecs($outpuFormat, $outputPath);
            }
        } finally {
            foreach ($clipsFiles as $clipFile) {
                @unlink($clipFile);
            }
        }

        if (!file_exists($outputPath)) {
            throw new \RuntimeException(sprintf('Failed to create summary video'));
        }


        // TODO return the correct family and MIME type
        return new OutputFile(
            $outputPath,
            'application/octet-stream',
            FamilyEnum::Unknown
        );
    }
}
