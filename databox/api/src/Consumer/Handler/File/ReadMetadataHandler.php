<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use App\Asset\FileFetcher;
use App\Consumer\Handler\Asset\NewAssetIntegrationCollectionHandler;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Metadata\MetadataNormalizer;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

class ReadMetadataHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'read_file_metadata';
    private MetadataNormalizer $metadataNormalizer;
    private FileFetcher $fileFetcher;
    private EventProducer $eventProducer;
    private MetadataManipulator $metadataManipulator;

    public function __construct(MetadataManipulator $metadataManipulator, MetadataNormalizer $metadataNormalizer, FileFetcher $fileFetcher, EventProducer $eventProducer)
    {
        $this->metadataNormalizer = $metadataNormalizer;
        $this->fileFetcher = $fileFetcher;
        $this->eventProducer = $eventProducer;
        $this->metadataManipulator = $metadataManipulator;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $assetId = $payload['id'];

        $em = $this->getEntityManager();

        $asset = $em->find(Asset::class, $assetId);
        if (!$asset instanceof Asset) {
            throw new ObjectNotFoundForHandlerException(Asset::class, $assetId, __CLASS__);
        }

        $file = $asset->getSource();
        if (!$file instanceof File) {
            return;
        }

        $fetchedFilePath = $this->fileFetcher->getFile($file);
        try {
            $fo = new \SplFileObject($fetchedFilePath);
            $meta = $this->metadataManipulator->getAllMetadata($fo);
            $norm = $this->metadataNormalizer->normalize($meta);

            $file->setMetadata($norm);
            unset($norm, $meta);

            $em->persist($file);
            $em->flush();

            $this->eventProducer->publish(InitializeAttributesHandler::createEvent($assetId));
        } finally {
            @unlink($fetchedFilePath);
        }

        $this->eventProducer->publish(NewAssetIntegrationCollectionHandler::createEvent($asset->getId()));
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }

    public static function createEvent(string $assetId): EventMessage
    {
        return new EventMessage(self::EVENT, [
            'id' => $assetId,
        ]);
    }
}
