<?php

declare(strict_types=1);

namespace App\Border\Consumer\Handler;

use ApiPlatform\Core\Api\IriConverterInterface;
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

class FileEntranceHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'file_entrance';

    private BorderManager $borderManager;
    private EventProducer $eventProducer;
    private UploaderClient $uploaderClient;

    public function __construct(
        BorderManager $borderManager,
        EventProducer $eventProducer,
        UploaderClient $uploaderClient
    )
    {
        $this->borderManager = $borderManager;
        $this->eventProducer = $eventProducer;
        $this->uploaderClient = $uploaderClient;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();

        $em = $this->getEntityManager();
        $workspaceId = $payload['workspaceId'];
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
        if ($file instanceof File) {
            $this->eventProducer->publish(new EventMessage(NewAssetFromBorderHandler::EVENT, [
                'fileId' => $file->getId(),
                'userId' => $payload['userId'],
                'title' => $payload['title'] ?? null,
                'filename' => $inputFile->getName(),
                'destinations' => $payload['destinations'],
            ]));
        } else {
            // TODO place into quarantine
        }
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
