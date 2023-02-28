<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use App\Asset\FileFetcher;
use App\Entity\Core\File;
use App\Storage\FileManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use GuzzleHttp\Psr7\Header;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class ImportFileHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'import_file';

    private FileFetcher $fileFetcher;
    private FileManager $fileManager;

    public function __construct(
        FileManager $fileManager,
        FileFetcher $fileFetcher,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->fileFetcher = $fileFetcher;
        $this->fileManager = $fileManager;
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

        if (!$file->isPathPublic()) {
            throw new InvalidArgumentException(sprintf('Import error: Source of file "%s" is not publicly accessible', $file->getId()));
        }

        if (File::STORAGE_URL !== $file->getStorage()) {
            throw new InvalidArgumentException(sprintf('Import error: Storage of file "%s" should be "%s"', $file->getId(), File::STORAGE_URL));
        }

        $headers = [];
        $src = $this->fileFetcher->getFile($file, $headers);

        if (isset($headers['Content-Length'])) {
            $size = Header::parse($headers['Content-Length']);
            if (null === $file->getSize() && !empty($size)) {
                $file->setSize((int) $size[0][0]);
            }
        }
        $mimeType = null;
        if (isset($headers['Content-Type'])) {
            $type = Header::parse($headers['Content-Type']);
            if (null === $file->getType() && !empty($type)) {
                $mimeType = $type[0][0];
            }
        }

        $finalPath = $this->fileManager->storeFile(
            $file->getWorkspace(),
            $src,
            $mimeType,
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
