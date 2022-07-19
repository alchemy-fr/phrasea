<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use App\Asset\OriginalRenditionManager;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\File;
use App\Phraseanet\PhraseanetGenerateRenditionsManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;

class NewAssetFromBorderHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'new_asset_from_border';

    private OriginalRenditionManager $originalRenditionManager;
    private PhraseanetGenerateRenditionsManager $generateRenditionsManager;

    public function __construct(PhraseanetGenerateRenditionsManager $generateRenditionsManager, OriginalRenditionManager $originalRenditionManager)
    {
        $this->originalRenditionManager = $originalRenditionManager;
        $this->generateRenditionsManager = $generateRenditionsManager;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $id = $payload['fileId'];
        $collectionIds = $payload['collections'];

        $em = $this->getEntityManager();
        $file = $em->find(File::class, $id);
        if (!$file instanceof File) {
            throw new ObjectNotFoundForHandlerException(File::class, $id, __CLASS__);
        }

        $collections = $em->getRepository(Collection::class)->findByIds($collectionIds);

        $asset = new Asset();
        $asset->setFile($file);
        $asset->setOwnerId($payload['userId']);
        $asset->setTitle($payload['title'] ?? $payload['filename'] ?? $file->getPath());
        $workspace = $file->getWorkspace();
        $asset->setWorkspace($workspace);

        $this->originalRenditionManager->assignFileToOriginalRendition($asset, $file);

        foreach ($collections as $collection) {
            $assetCollection = $asset->addToCollection($collection);
            $em->persist($assetCollection);
        }

        $em = $this->getEntityManager();
        $em->persist($asset);
        $em->flush();

        $this->generateRenditionsManager->generateRenditions($asset);
    }

    public static function createEvent(
        string $userId,
        string $fileId,
        array $collections,
        ?string $title = null,
        ?string $filename = null
    ): EventMessage {
        return new EventMessage(self::EVENT, [
            'userId' => $userId,
            'fileId' => $fileId,
            'collections' => $collections,
            'title' => $title,
            'filename' => $filename,
        ]);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
