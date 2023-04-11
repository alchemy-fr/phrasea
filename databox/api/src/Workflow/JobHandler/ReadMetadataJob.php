<?php

declare(strict_types=1);

namespace App\Workflow\JobHandler;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\RunContext;
use App\Asset\FileFetcher;
use App\Entity\Core\File;
use App\Metadata\MetadataNormalizer;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use Doctrine\ORM\EntityManagerInterface;

class ReadMetadataJob implements ActionInterface
{
    public function __construct(
        private readonly MetadataNormalizer $metadataNormalizer,
        private readonly FileFetcher $fileFetcher,
        private readonly EntityManagerInterface $em,
    )
    {
    }

    public function handle(RunContext $context): void
    {
        $inputs = $context->getInputs();
        $id = $inputs['fileId'];

        $file = $this->em->find(File::class, $id);
        if (!$file instanceof File) {
            throw new ObjectNotFoundForHandlerException(File::class, $id, __CLASS__);
        }

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
