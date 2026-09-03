<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Consumer\Handler\File\DeleteFileFromStorage;
use App\Consumer\Handler\File\DeleteFilesIfOrphan;
use App\Consumer\Handler\File\ReevaluateFileAnalyses;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetFileVersion;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Repository\Core\FileDuplicateRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(Events::preRemove)]
readonly class FileListener implements EventSubscriber
{
    public function __construct(
        private PostFlushStack $postFlushStack,
        private FileDuplicateRepository $fileDuplicateRepository,
    ) {
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $object = $args->getObject();

        if ($object instanceof Asset) {
            $this->addFileToDelete($object->getSource());
        } elseif ($object instanceof AssetRendition
            || $object instanceof AssetFileVersion
        ) {
            $this->addFileToDelete($object->getFile());
        } elseif ($object instanceof File) {
            $path = null;
            if (File::STORAGE_S3_MAIN === $object->getStorage()) {
                $path = $object->getPath();
            }

            if (null !== $path) {
                $this->postFlushStack->addBusMessage(new DeleteFileFromStorage([$path]));
            }

            // Resolve the affected analyses before the DB cascade removes the duplicate links.
            $ownerFileIds = array_values(array_diff(
                $this->fileDuplicateRepository->findOwnerFileIdsByDuplicateFileIds([$object->getId()]),
                [$object->getId()],
            ));
            if (!empty($ownerFileIds)) {
                $this->postFlushStack->addBusMessage(new ReevaluateFileAnalyses($ownerFileIds));
            }
        }
    }

    private function addFileToDelete(?File $file): void
    {
        if (!$file || File::STORAGE_S3_MAIN !== $file->getStorage()) {
            return;
        }

        $this->postFlushStack->addBusMessage(new DeleteFilesIfOrphan([$file->getId()]));
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::preRemove,
        ];
    }
}
