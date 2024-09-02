<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\Module;

use Alchemy\RenditionFactory\Transformation\TransformationContext;
use Alchemy\RenditionFactory\File\InputFile;
use Alchemy\RenditionFactory\File\OutputFile;
use Alchemy\RenditionFactory\Transformation\TransformerFormatHelper;

class VideoTransformationModule implements TransformationModuleInterface {
    public function getDocumentation(): string
    {
        return 'Hello';
    }

    public function process(InputFile $inputFile, array $options, TransformationContext $context): OutputFile
    {
        $outputFormat = TransformerFormatHelper::getOutputFormatFromOptions($inputFile->getType(), $options);
        // $inputFile === 'file.ai';
        $file = $context->normalizeFile($inputFile, $this->getSupportedFormats());

        // Process $file
        $outputFile = magic_is_here($file);

        [$videoFile, $audioFile] = $this->splitTracks($inputFile);
        foreach ($options['video_transformations'] as $videoTransform) {
            $videoOutput = $context->processModule($videoTransform, $videoFile);
        }
        foreach ($options['audio_transformations'] as $audioTransformation) {
            $audioOutput = $context->processModule($audioTransformation, $audioFile);
        }

        // create video from $audioOutput;

        return $context->convert($outputFile, $outputFormat);
    }

    private function getSupportedFormats(): array
    {
        return [
            'jpeg',
            'png',
        ];
    }
}
