<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use App\Asset\FileCopier;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;

class CopyFileToAssetHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'copy_file_to_asset';

    public function __construct(private readonly FileCopier $fileCopier)
    {
    }

    public static function createEvent(string $assetId, string $fileId): EventMessage
    {
        $payload = [
            'assetId' => $assetId,
            'fileId' => $fileId,
        ];

        return new EventMessage(self::EVENT, $payload);
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $assetId = $payload['assetId'];
        $fileId = $payload['fileId'];

        $em = $this->getEntityManager();
        $asset = $em->find(Asset::class, $assetId);
        if (!$asset instanceof Asset) {
            throw new ObjectNotFoundForHandlerException(AssetRendition::class, $assetId, self::class);
        }

        $file = $em->find(File::class, $fileId);
        if (!$file instanceof File) {
            throw new ObjectNotFoundForHandlerException(File::class, $fileId, self::class);
        }

        $copy = $this->fileCopier->copyFile($file, $asset->getWorkspace());

        $asset->setSource($copy);
        $asset->setNoFileVersion(true);

        $em->persist($asset);
        $em->flush();
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
