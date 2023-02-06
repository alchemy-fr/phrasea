<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use App\Asset\AssetManager;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;

class AssignSourceFileToAssetHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'assign_new_file_to_asset';

    private AssetManager $assetManager;

    public function __construct(
        AssetManager $assetManager
    ) {
        $this->assetManager = $assetManager;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $fileId = $payload['fileId'];
        $assetId = $payload['assetId'];

        $em = $this->getEntityManager();
        $file = $em->find(File::class, $fileId);
        if (!$file instanceof File) {
            throw new ObjectNotFoundForHandlerException(File::class, $fileId, __CLASS__);
        }

        $asset = $em->find(Asset::class, $assetId);
        if (!$asset instanceof Asset) {
            throw new ObjectNotFoundForHandlerException(Asset::class, $assetId, __CLASS__);
        }

        $this->assetManager->assignNewAssetSourceFile($asset, $file);
    }

    public static function createEvent(string $assetId, string $fileId): EventMessage
    {
        return new EventMessage(self::EVENT, [
            'assetId' => $assetId,
            'fileId' => $fileId,
        ]);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
