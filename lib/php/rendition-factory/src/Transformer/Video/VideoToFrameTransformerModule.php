<?php

namespace Alchemy\RenditionFactory\Transformer\Video;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use FFMpeg;
use FFMpeg\Media\Video;

final readonly class VideoToFrameTransformerModule extends VideoTransformerBase implements TransformerModuleInterface
{
    public static function getName(): string
    {
        return 'video_to_frame';
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        $commonArgs = new ModuleCommonArgsDTO($this->formats, $options, $context, $this->optionsResolver);
        $outputFormat = $commonArgs->getOutputFormat();

        /** @var Video $video */
        $video = $commonArgs->getFFMpeg()->open($inputFile->getPath());

        $resolverContext = [
            'metadata' => $context->getTemplatingContext(),
            'input' => $video->getStreams()->videos()->first()->all(),
        ];

        $fromSeconds = $this->optionsResolver->resolveOption($options['from_seconds'] ?? 0, $resolverContext);

        $frame = $video->frame(FFMpeg\Coordinate\TimeCode::fromSeconds($fromSeconds));
        $outputPath = $context->createTmpFilePath($commonArgs->getExtension());

        $frame->save($outputPath);

        unset($frame, $video);
        gc_collect_cycles();

        return new OutputFile(
            $outputPath,
            $outputFormat->getMimeType(),
            $outputFormat->getFamily(),
            false,
        );
    }
}
