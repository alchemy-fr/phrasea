<?php

namespace Alchemy\RenditionFactory\Transformer\Video;

use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\FileFamilyGuesser;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use FFMpeg;

final readonly class VideoToFrameTransformerModule implements TransformerModuleInterface
{
    public function __construct()
    {
    }

    public static function getName(): string
    {
        return 'video_to_frame';
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContextInterface $context): OutputFileInterface
    {
        $ffmpeg = FFMpeg\FFMpeg::create(); // (new FFMpeg\FFMpeg)->open('/path/to/video');

        $from_seconds = $options['from_seconds'] ?? 0;
        $extension = $options['extension'] ?? '.jpg';

        $video = $ffmpeg->open($inputFile->getPath());
        $frame = $video->frame(FFMpeg\Coordinate\TimeCode::fromSeconds($from_seconds));
        $outputPath = $context->createTmpFilePath($extension);

        $frame->save($outputPath);

        unset($frame, $video);
        gc_collect_cycles();

        $mimeType = $context->guessMimeTypeFromPath($outputPath);

        $fileFamilyGuesser = new FileFamilyGuesser();
        $family = $fileFamilyGuesser->getFamily($outputPath, $mimeType);

        return new OutputFile(
            $outputPath,
            $mimeType,
            $family
        );
    }
}
