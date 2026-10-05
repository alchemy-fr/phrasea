<?php

declare(strict_types=1);

namespace App\Service\Workflow\Action;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use Alchemy\Workflow\Executor\RunContext;
use App\Entity\Core\Asset;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;
use App\Service\Asset\FileFetcher;
use App\Service\Metadata\MetadataNormalizer;

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

    #[\Override]
    protected function shouldRun(Asset $asset): bool
    {
        $source = $asset->getSource();
        if (null === $source) {
            return false;
        }

        // A private remote source cannot be downloaded: nothing to read.
        return $this->fileFetcher->isFetchable($source);
    }
}
