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
        $this->prepare($options, $context);

        /** @var Video $video */
        $video = $this->ffmpeg->open($inputFile->getPath());

        $resolverContext = [
            'metadata' => $context->getTemplatingContext(),
            'input' => $video->getStreams()->videos()->first()->all(),
        ];

        $fromSeconds = $this->optionsResolver->resolveOption($options['from_seconds'] ?? 0, $resolverContext);

        $frame = $video->frame(FFMpeg\Coordinate\TimeCode::fromSeconds($fromSeconds));
        $outputPath = $context->createTmpFilePath($this->extension);

        $frame->save($outputPath);

        unset($frame, $video);
        gc_collect_cycles();

        return new OutputFile(
            $outputPath,
            $this->outputFormat->getMimeType(),
            $this->outputFormat->getFamily(),
        );
    }
}
