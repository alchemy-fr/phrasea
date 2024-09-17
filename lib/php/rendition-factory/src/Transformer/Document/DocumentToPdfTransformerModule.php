<?php

namespace Alchemy\RenditionFactory\Transformer\Document;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\DTO\OutputFile;
use Alchemy\RenditionFactory\DTO\InputFileInterface;
use Alchemy\RenditionFactory\DTO\OutputFileInterface;
use Alchemy\RenditionFactory\Transformer\Document\Unoserver\Unoconvert;
use Alchemy\RenditionFactory\Transformer\TransformationContext;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Symfony\Component\Filesystem\Filesystem;

final readonly class DocumentToPdfTransformerModule implements TransformerModuleInterface
{
    public static function getName(): string
    {
        return 'DocumentToPdf';
    }

    public function transform(InputFileInterface $inputFile, array $options, TransformationContext $context): OutputFileInterface
    {
        $newPath = $context->createTmpFilePath('pdf');

        if ($inputFile->getType() === 'application/pdf') {
            // if input is already a pdf get and copy the original
            $filesystem = new Filesystem();
            $filesystem->copy($inputFile->getPath(), $newPath);

            return new OutputFile(
                $newPath,
                'application/pdf',
                FamilyEnum::Document
            );

        }

        $unoconvert = new Unoconvert();

        $pageRange = null;

        if (isset($options['page-range'])) {
            $pageRange = $options['page-range'];
        }

        $unoconvert->transcode($inputFile->getPath(), $newPath, $pageRange);

        return new OutputFile(
            $newPath,
            'application/pdf',
            FamilyEnum::Document
        );
    }
}
