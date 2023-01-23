<?php

declare(strict_types=1);

namespace App\Border\Consumer\Handler\Uploader;

use App\Border\UploaderClient;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;

class UploaderAckAssetHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'uploader_ack_asset';

    private UploaderClient $uploaderClient;

    public function __construct(UploaderClient $uploaderClient)
    {
        $this->uploaderClient = $uploaderClient;
    }

    public static function createEvent(string $baseUrl, string $assetId, string $token): EventMessage
    {
        return new EventMessage(self::EVENT, [
            'baseUrl' => $baseUrl,
            'assetId' => $assetId,
            'token' => $token,
        ]);
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $this->uploaderClient->ackAsset($payload['baseUrl'], $payload['assetId'], $payload['token']);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
