<?php

declare(strict_types=1);

namespace App\Border\Consumer\Handler\Uploader;

use App\Border\BorderManager;
use App\Border\Model\InputFile;
use App\Border\UploaderClient;
use App\Consumer\Handler\File\AssignSourceFileToAssetHandler;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use InvalidArgumentException;

class UploaderNewFileHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'uploader_new_file';

    private BorderManager $borderManager;
    private EventProducer $eventProducer;
    private UploaderClient $uploaderClient;

    public function __construct(
        BorderManager $borderManager,
        EventProducer $eventProducer,
        UploaderClient $uploaderClient
    ) {
        $this->borderManager = $borderManager;
        $this->eventProducer = $eventProducer;
        $this->uploaderClient = $uploaderClient;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $em = $this->getEntityManager();

        $assetData = $this->uploaderClient->getAsset($payload['baseUrl'], $payload['assetId'], $payload['token']);

        $assetId = $assetData['data']['targetAsset'];
        $uploadToken = $assetData['data']['uploadToken'];

        $asset = $em->find(Asset::class, $assetId);
        if (!$asset instanceof Asset) {
            throw new ObjectNotFoundForHandlerException(Asset::class, $assetId, __CLASS__);
        }
        if ($uploadToken !== $asset->getPendingUploadToken()) {
            throw new InvalidArgumentException('Unexpected upload token, skipping...');
        }

        $inputFile = new InputFile(
            $assetData['originalName'],
            $assetData['mimeType'],
            $assetData['size'],
            $assetData['url'],
        );

        $file = $this->borderManager->acceptFile($inputFile, $asset->getWorkspace());

        $this->eventProducer->publish(UploaderAckAssetHandler::createEvent(
            $payload['baseUrl'], $payload['assetId'], $payload['token']
        ));

        if ($file instanceof File) {
            $this->eventProducer->publish(AssignSourceFileToAssetHandler::createEvent(
                $assetId,
                $file->getId(),
            ));
        }
    }

    public static function createEvent(string $assetId, string $baseUrl, string $token): EventMessage
    {
        return new EventMessage(self::EVENT, [
            'assetId' => $assetId,
            'baseUrl' => $baseUrl,
            'token' => $token,
        ]);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
