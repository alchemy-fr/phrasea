<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use App\Entity\Core\File;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class DeleteFilesIfOrphanHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(DeleteFilesIfOrphan $message): void
    {
        $repo = $this->em->getRepository(File::class);
        foreach ($message->getIds() as $id) {
            $this->em->wrapInTransaction(function () use ($id, $repo): void {
                $file = $repo->find($id);
                if ($file instanceof File) {
                    $path = null;
                    if (File::STORAGE_S3_MAIN === $file->getStorage()) {
                        $path = $file->getPath();
                    }

                    $this->em->remove($file);
                    $this->em->flush();

                    if (null !== $path) {
                        $this->bus->dispatch(new DeleteFileFromStorage([$path]));
                    }
                }
            });
        }
    }
}
