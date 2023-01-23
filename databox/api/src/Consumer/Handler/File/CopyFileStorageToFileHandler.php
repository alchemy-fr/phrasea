<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use App\Asset\FileFetcher;
use App\Entity\Core\File;
use App\Storage\FileManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;

class CopyFileStorageToFileHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'copy_file_storage';

    private FileManager $fileManager;
    private FileFetcher $fileFetcher;

    public function __construct(
        FileManager $fileManager,
        FileFetcher $fileFetcher
    ) {
        $this->fileManager = $fileManager;
        $this->fileFetcher = $fileFetcher;
    }

    public static function createEvent(string $fileId): EventMessage
    {
        $payload = [
            'id' => $fileId,
        ];

        return new EventMessage(self::EVENT, $payload);
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $id = $payload['id'];

        $em = $this->getEntityManager();
        $file = $em->find(File::class, $id);
        if (!$file instanceof File) {
            throw new ObjectNotFoundForHandlerException(File::class, $id, __CLASS__);
        }

        $headers = [];
        $src = $this->fileFetcher->getFile($file, $headers);

        $finalPath = $this->fileManager->storeFile(
            $file->getWorkspace(),
            $src,
            $file->getType(),
            $file->getExtension(),
           null
        );

        $file->setPath($finalPath);
        $file->setStorage(File::STORAGE_S3_MAIN);
        $file->setPathPublic(true);
        $em->persist($file);
        $em->flush();

        unlink($src);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
