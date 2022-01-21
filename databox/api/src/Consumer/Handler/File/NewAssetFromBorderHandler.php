<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use App\Asset\OriginalRenditionManager;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\File;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

class NewAssetFromBorderHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'new_asset_from_border';

    private EventProducer $eventProducer;
    private OriginalRenditionManager $originalRenditionManager;

    public function __construct(EventProducer $eventProducer, OriginalRenditionManager $originalRenditionManager)
    {
        $this->eventProducer = $eventProducer;
        $this->originalRenditionManager = $originalRenditionManager;
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
        $asset->setWorkspace($file->getWorkspace());

        $this->originalRenditionManager->assignFileToOriginalRendition($asset, $file);

        foreach ($collections as $collection) {
            $assetCollection = $asset->addToCollection($collection);
            $em->persist($assetCollection);
        }

        $em = $this->getEntityManager();
        $em->persist($asset);
        $em->flush();

        $this->eventProducer->publish(new EventMessage(GenerateAssetRenditionsHandler::EVENT, [
            'id' => $asset->getId(),
        ]));
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
