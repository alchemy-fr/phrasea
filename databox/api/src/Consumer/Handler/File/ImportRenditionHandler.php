<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use App\Asset\FileUrlResolver;
use App\Border\FileDownloader;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Storage\FileManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use GuzzleHttp\Psr7\Header;
use Psr\Log\LoggerInterface;

class ImportRenditionHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'import_rendition';

    private FileUrlResolver $fileUrlResolver;
    private FileDownloader $downloader;
    private FileManager $fileManager;

    public function __construct(
        FileUrlResolver $fileUrlResolver,
        FileManager $fileManager,
        FileDownloader $downloader,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->fileUrlResolver = $fileUrlResolver;
        $this->downloader = $downloader;
        $this->fileManager = $fileManager;
    }

    public static function createEvent(string $renditionId): EventMessage
    {
        $payload = [
            'id' => $renditionId,
        ];

        return new EventMessage(self::EVENT, $payload);
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $id = $payload['id'];

        $em = $this->getEntityManager();
        $rendition = $em->find(AssetRendition::class, $id);
        if (!$rendition instanceof AssetRendition) {
            throw new ObjectNotFoundForHandlerException(AssetRendition::class, $id, __CLASS__);
        }

        $file = $rendition->getFile();

        if (!$file instanceof File) {
            $this->logger->warning(sprintf('%s error: AssetRendition %s has no file', __CLASS__, $rendition->getId()));

            return;
        }

        $headers = [];
        $src = $this->downloader->download($this->fileUrlResolver->resolveUrl($file), $headers);

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
            $rendition->getAsset()->getWorkspace(),
            $src,
            $mimeType,
            $file->getExtension(),
           null
        );

        $file->setPath($finalPath);
        $file->setStorage(File::STORAGE_S3_MAIN);
        $em->persist($file);
        $em->flush();

        unlink($src);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
