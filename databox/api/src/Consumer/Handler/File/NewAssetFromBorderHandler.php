<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use App\Asset\AssetManager;
use App\Consumer\Handler\Asset\NewAssetIntegrationsHandler;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\File;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;

class NewAssetFromBorderHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'new_asset_from_border';

    private AssetManager $assetManager;

    public function __construct(AssetManager $assetManager) {
        $this->assetManager = $assetManager;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $id = $payload['fileId'];
        $collectionIds = $payload['collections'];
        $formData = $payload['formData'] ?? [];
        $locale = $payload['locale'] ?? null;

        $em = $this->getEntityManager();
        $file = $em->find(File::class, $id);
        if (!$file instanceof File) {
            throw new ObjectNotFoundForHandlerException(File::class, $id, __CLASS__);
        }

        $collections = $em->getRepository(Collection::class)->findByIds($collectionIds);

        $asset = new Asset();

        $asset->setSource($file);
        $asset->setOwnerId($payload['userId']);
        $asset->setTitle($payload['title'] ?? $payload['filename'] ?? $file->getPath());
        $workspace = $file->getWorkspace();
        $asset->setWorkspace($workspace);

        foreach ($collections as $collection) {
            $assetCollection = $asset->addToCollection($collection);
            $em->persist($assetCollection);
        }

        $this->assetManager->assignNewAssetSourceFile($asset, $file, $formData, $locale);
    }

    public static function createEvent(
        string $userId,
        string $fileId,
        array $collections,
        ?string $title = null,
        ?string $filename = null,
        ?array $formData = null,
        ?string $locale = null
    ): EventMessage {
        return new EventMessage(self::EVENT, [
            'userId' => $userId,
            'fileId' => $fileId,
            'collections' => $collections,
            'title' => $title,
            'filename' => $filename,
            'formData' => $formData,
            'locale' => $locale,
        ]);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
