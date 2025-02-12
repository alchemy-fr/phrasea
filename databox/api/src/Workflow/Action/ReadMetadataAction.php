<?php

declare(strict_types=1);

namespace App\Workflow\Action;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use Alchemy\Workflow\Executor\RunContext;
use App\Asset\FileFetcher;
use App\Entity\Core\Asset;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;
use App\Metadata\MetadataNormalizer;

class ReadMetadataAction extends AbstractIntegrationAction implements IfActionInterface
{
    public function __construct(
        private readonly MetadataManipulator $metadataManipulator,
        private readonly MetadataNormalizer $metadataNormalizer,
        private readonly FileFetcher $fileFetcher,
    ) {
    }

    public function doHandle(RunContext $context): void
    {
        $asset = $this->getAsset($context);
        $file = $asset->getSource();

        $fetchedFilePath = $this->fileFetcher->getFile($file);
        try {
            $fo = new \SplFileObject($fetchedFilePath);
            $meta = $this->metadataManipulator->getAllMetadata($fo);
            $norm = $this->metadataNormalizer->normalize($meta);

            $file->setMetadata($norm);
            unset($norm, $meta);

            $this->em->persist($file);
            $this->em->flush();
        } finally {
            @unlink($fetchedFilePath);
        }
    }

    protected function shouldRun(Asset $asset): bool
    {
        if (null === $asset->getSource()) {
            return false;
        }

        return true;
    }
}
