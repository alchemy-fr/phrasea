<?php

declare(strict_types=1);

namespace App\Border\Consumer\Handler\Uploader;

use App\Border\BorderManager;
use App\Border\Model\InputFile;
use App\Border\UploaderClient;
use App\Consumer\Handler\File\NewAssetFromBorderHandler;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

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
        $workspaceId = $payload['workspaceId'];
        $formData = $payload['formData'] ?? null;
        $workspace = $em->find(Workspace::class, $workspaceId);
        if (!$workspace instanceof Workspace) {
            throw new ObjectNotFoundForHandlerException(Workspace::class, $workspaceId, __CLASS__);
        }

        $assetData = $this->uploaderClient->getAsset($payload['baseUrl'], $payload['assetId'], $payload['token']);

        $inputFile = new InputFile(
            $assetData['originalName'],
            $assetData['mimeType'],
            $assetData['size'],
            $assetData['url'],
        );

        $file = $this->borderManager->acceptFile($inputFile, $workspace);

        $this->eventProducer->publish(UploaderAckAssetHandler::createEvent(
            $payload['baseUrl'], $payload['assetId'], $payload['token']
        ));

        if ($file instanceof File) {
            $this->eventProducer->publish(NewAssetFromBorderHandler::createEvent(
                $payload['userId'],
                $file->getId(),
                $payload['collections'],
                $payload['title'] ?? null,
                $inputFile->getName(),
                $formData,
                $payload['locale'] ?? null
            ));
        }
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
