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
use Symfony\Component\HttpClient\Exception\ClientException;

class ImportFileHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'import_file';

    public function __construct(
        private readonly FileManager $fileManager,
        private readonly FileFetcher $fileFetcher,
    ) {
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
            throw new ObjectNotFoundForHandlerException(File::class, $id, self::class);
        }

        if (!$file->isPathPublic()) {
            throw new \InvalidArgumentException(sprintf('Import error: Source of file "%s" is not publicly accessible', $file->getId()));
        }

        if (File::STORAGE_URL !== $file->getStorage()) {
            $this->logger->error(sprintf('Import error: Storage of file "%s" should be "%s"', $file->getId(), File::STORAGE_URL));

            // File may have already been imported
            return;
        }

        $headers = [];

        try {
            $src = $this->fileFetcher->getFile($file, $headers);
        } catch (ClientException $e) {
            if (404 === $e->getResponse()->getStatusCode()) {
                $this->logger->error($e->getMessage());

                return;
            }

            throw $e;
        }

        try {
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
        } finally {
            unlink($src);
        }
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
