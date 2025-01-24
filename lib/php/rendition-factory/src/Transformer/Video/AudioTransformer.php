<?php

namespace Alchemy\RenditionFactory\Transformer\Video;

use Alchemy\RenditionFactory\Config\ModuleOptionsResolver;
use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use FFMpeg;
use FFMpeg\Format\FormatInterface as FFMpegFormatInterface;
use FFMpeg\Media\Audio;

class AudioTransformer
{
    public function __construct(private readonly ModuleOptionsResolver $optionsResolver)
    {
    }

    public function doAudio(array $options, InputFileInterface $inputFile, TransformationContextInterface $transformationContext, ModuleCommonArgs $commonArgs): OutputFileInterface
    {
        $outputFormat = $commonArgs->getOutputFormat();
        $format = $outputFormat->getFormat();

        if (!method_exists($commonArgs->getOutputFormat(), 'getFFMpegFormat')) {
            throw new \InvalidArgumentException('format %s does not declare FFMpeg format', $format);
        }
        /** @var FFMpegFormatInterface $FFMpegFormat */
        $FFMpegFormat = $commonArgs->getOutputFormat()->getFFMpegFormat();

        /** @var Audio $audio */
        $audio = $commonArgs->getFFMpeg()->open($inputFile->getPath());

        $resolverContext = $transformationContext->getTemplatingContext();

        if ($audioCodec = $this->optionsResolver->resolveOption($options['audio_codec'] ?? null, $resolverContext)) {
            if (!in_array($audioCodec, $FFMpegFormat->getAvailableAudioCodecs())) {
                throw new \InvalidArgumentException(sprintf('Invalid audio codec %s for format %s', $audioCodec, $format));
            }
            $FFMpegFormat->setAudioCodec($audioCodec);
        }
        if (null !== ($audioKilobitrate = $this->optionsResolver->resolveOption($options['audio_kilobitrate'] ?? null, $resolverContext))) {
            $audioKilobitrate = (int) $audioKilobitrate;
            if (!method_exists($FFMpegFormat, 'setAudioKiloBitrate')) {
                throw new \InvalidArgumentException(sprintf('format %s does not support audio_kilobitrate', $format));
            }
            $FFMpegFormat->setAudioKiloBitrate($audioKilobitrate);
        }
        if (null !== ($audioChannels = $this->optionsResolver->resolveOption($options['audio_channels'] ?? null, $resolverContext))) {
            $audioChannels = (int) $audioChannels;
            if (!method_exists($FFMpegFormat, 'setAudioChannels')) {
                throw new \InvalidArgumentException(sprintf('format %s does not support audio_channels', $format));
            }
            $FFMpegFormat->setAudioChannels($audioChannels);
        }

        $filters = array_values(array_filter($options['filters'] ?? [],
            function ($filter) use ($resolverContext) {
                return $this->optionsResolver->resolveOption($filter['enabled'] ?? true, $resolverContext);
            }));

        $isProjection = true;

        foreach ($filters as $filter) {
            if (!method_exists($this, $filter['name'])) {
                throw new \InvalidArgumentException(sprintf('Invalid filter: %s', $filter['name']));
            }

            /* @uses self::clip()
             */
            $this->{$filter['name']}($audio, $filter, $resolverContext, $transformationContext, $isProjection);
        }

        $outputPath = $transformationContext->createTmpFilePath($commonArgs->getExtension());

        $audio->save($FFMpegFormat, $outputPath);

        unset($audio);
        gc_collect_cycles();

        return new OutputFile(
            $outputPath,
            $outputFormat->getMimeType(),
            $outputFormat->getFamily(),
            $isProjection
        );
    }

    private function clip(Audio $audio, array $options, array $resolverContext, TransformationContextInterface $transformationContext, bool &$isProjection): void
    {
        $start = $this->optionsResolver->resolveOption($options['start'] ?? 0, $resolverContext);
        $startAsTimecode = FFMpegHelper::optionAsTimecode($start);

        if (null === $startAsTimecode) {
            throw new \InvalidArgumentException('Invalid start for filter "clip"');
        }
        $start = FFMpegHelper::timecodeToseconds($startAsTimecode);
        if ($start > 0.0) {
            $isProjection = false;
        }

        $duration = $this->optionsResolver->resolveOption($options['duration'] ?? null, $resolverContext);
        if (null !== $duration) {
            $durationAsTimecode = FFMpegHelper::optionAsTimecode($duration);
            if (null === $durationAsTimecode) {
                throw new \InvalidArgumentException('Invalid duration for filter "clip"');
            }
            $isProjection = false;
            $transformationContext->log(sprintf("  Applying 'clip' filter: start=%s (%.02f), duration=%s (%.02f)", $startAsTimecode, $start, $durationAsTimecode, $duration));
        } else {
            $durationAsTimecode = null;
            $transformationContext->log(sprintf("  Applying 'clip' filter: start=%s (%.02f), duration=null", $startAsTimecode, $start));
        }

        $audio->addFilter(new FFMpeg\Filters\Audio\AudioClipFilter($startAsTimecode, $durationAsTimecode));
    }
}
