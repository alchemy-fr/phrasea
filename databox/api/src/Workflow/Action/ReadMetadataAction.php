<?php

declare(strict_types=1);

namespace App\Workflow\Action;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\RunContext;
use App\Asset\FileFetcher;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Metadata\MetadataNormalizer;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use Doctrine\ORM\EntityManagerInterface;

readonly class ReadMetadataAction implements ActionInterface
{
    public function __construct(
        private MetadataNormalizer $metadataNormalizer,
        private FileFetcher $fileFetcher,
        private EntityManagerInterface $em,
    )
    {
    }

    public function handle(RunContext $context): void
    {
        $inputs = $context->getInputs();
        $id = $inputs['assetId'];

        $asset = $this->em->find(Asset::class, $id);
        if (!$asset instanceof Asset) {
            throw new ObjectNotFoundForHandlerException(Asset::class, $id, __CLASS__);
        }

        $file = $asset->getSource();

        $fetchedFilePath = $this->fileFetcher->getFile($file);
        try {
            $mm = new MetadataManipulator();
            $meta = $mm->getAllMetadata(new \SplFileObject($fetchedFilePath));

            $file->setMetadata(
                $this->metadataNormalizer->normalize($meta)
            );
            unset($meta, $mm);

            $this->em->persist($file);
            $this->em->flush();
        } finally {
            @unlink($fetchedFilePath);
        }
    }
}
