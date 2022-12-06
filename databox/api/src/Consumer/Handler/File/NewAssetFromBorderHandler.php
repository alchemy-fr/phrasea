<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use App\Asset\OriginalRenditionManager;
use App\Attribute\AttributeDataExporter;
use App\Consumer\Handler\Asset\NewAssetIntegrationsHandler;
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

    private OriginalRenditionManager $originalRenditionManager;
    private AttributeDataExporter $attributeDataExporter;
    private EventProducer $eventProducer;

    public function __construct(
        OriginalRenditionManager $originalRenditionManager,
        AttributeDataExporter $attributeDataExporter,
        EventProducer $eventProducer
    ) {
        $this->originalRenditionManager = $originalRenditionManager;
        $this->attributeDataExporter = $attributeDataExporter;
        $this->eventProducer = $eventProducer;
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
        $asset->setFile($file);
        $asset->setOwnerId($payload['userId']);
        $asset->setTitle($payload['title'] ?? $payload['filename'] ?? $file->getPath());
        $workspace = $file->getWorkspace();
        $asset->setWorkspace($workspace);

        if (!empty($formData)) {
            $this->attributeDataExporter->importAttributes($asset, $formData, $locale);
        }

        $this->originalRenditionManager->assignFileToOriginalRendition($asset, $file);

        foreach ($collections as $collection) {
            $assetCollection = $asset->addToCollection($collection);
            $em->persist($assetCollection);
        }

        $em = $this->getEntityManager();
        $em->persist($asset);
        $em->flush();

        $this->eventProducer->publish(ReadMetadataHandler::createEvent(
            $file->getId()
        ));

        $this->eventProducer->publish(NewAssetIntegrationsHandler::createEvent($asset->getId()));
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
