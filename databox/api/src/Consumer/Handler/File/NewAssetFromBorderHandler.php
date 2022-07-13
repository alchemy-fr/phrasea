<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use App\Asset\OriginalRenditionManager;
use App\Consumer\Handler\Phraseanet\PhraseanetGenerateAssetRenditionsEnqueueMethodHandler;
use App\Consumer\Handler\Phraseanet\PhraseanetGenerateAssetRenditionsHandler;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
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

        if (Workspace::PHRASEANET_RENDITION_METHOD_SUBDEF_V3_API === $workspace->getPhraseanetRenditionMethod()) {
            $this->eventProducer->publish(PhraseanetGenerateAssetRenditionsHandler::createEvent($asset->getId()));
        } elseif (Workspace::PHRASEANET_RENDITION_METHOD_ENQUEUE === $workspace->getPhraseanetRenditionMethod()) {
            $this->eventProducer->publish(PhraseanetGenerateAssetRenditionsEnqueueMethodHandler::createEvent($asset->getId()));
        }
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
