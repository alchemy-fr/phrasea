<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Asset\FileUrlResolver;
use App\Border\FileDownloader;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Storage\RenditionPathGenerator;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use Psr\Log\LoggerInterface;

class ImportRenditionHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'import_rendition';

    private FileUrlResolver $fileUrlResolver;
    private RenditionPathGenerator $pathGenerator;
    private FileStorageManager $storageManager;
    private FileDownloader $downloader;

    public function __construct(
        FileUrlResolver $fileUrlResolver,
        RenditionPathGenerator $pathGenerator,
        FileStorageManager $storageManager,
        FileDownloader $downloader,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->fileUrlResolver = $fileUrlResolver;
        $this->pathGenerator = $pathGenerator;
        $this->storageManager = $storageManager;
        $this->downloader = $downloader;
    }

    public static function createEvent(string $renditionId, string $extension): EventMessage
    {
        $payload = [
            'id' => $renditionId,
            'extension' => $extension,
        ];

        return new EventMessage(self::EVENT, $payload);
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $id = $payload['id'];
        $extension = $payload['extension'] ?? null;

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

        $src = $this->downloader->download($this->fileUrlResolver->resolveUrl($file));
        $finalPath = $this->pathGenerator
            ->generatePath($rendition->getAsset()->getWorkspaceId(), $extension);

        $fd = fopen($src, 'r');
        $this->storageManager->storeStream($finalPath, $fd);
        fclose($fd);

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
