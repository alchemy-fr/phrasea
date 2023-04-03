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
            $this->logger->debug(sprintf("Asset id=%s has no source file", $assetId));
            return;
            // throw new ObjectNotFoundForHandlerException(File::class, $id, __CLASS__);
        }

        $fetchedFilePath = $this->fileFetcher->getFile($file);
        try {
            $mm = $this->metadataManipulator;
            $this->logger->debug(sprintf("MetadataManipulator service OK"));

            $mm->setLogger($this->logger);
            $this->logger->debug(sprintf("mm->setLoger() OK"));

            $fo = new \SplFileObject($fetchedFilePath);
            $this->logger->debug(sprintf("new SplFileObject(\"%s\") OK", $fetchedFilePath));

            $meta = $mm->getAllMetadata($fo);
            if(!is_null($meta)) {
                $this->logger->debug(sprintf("getAllMetadata() returned class \"%s\"", get_class($meta)));
            }
            else {
                $this->logger->debug(sprintf("getAllMetadata() returned null ???"));
            }

            $norm = $this->metadataNormalizer->normalize($meta);
            if(is_array($norm)) {
                $this->logger->debug(sprintf("metadataNormalizer returned array[%d]", count($norm)));
            }
            else if (is_null($norm)) {
                $this->logger->debug(sprintf("metadataNormalizer returned null ???"));
            }
            else {
                $this->logger->debug(sprintf("metadataNormalizer did not return an array ???"));
            }

            $file->setMetadata(
                $norm
            );
            unset($norm, $meta, $mm);

            $em = $this->getEntityManager();
            $em->persist($file);
            $em->flush();

            $this->logger->debug(sprintf("metadata persisted in file entity OK"));

            $this->eventProducer->publish(InitializeAttributes::createEvent($assetId));
        }
        catch (\Throwable $e) {
            $this->logger->debug(sprintf("Exception \"%s\" occured on %s[%d]???", $e->getMessage(), $e->getFile(), $e->getLine()));
            throw($e);
        }
        finally {
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
