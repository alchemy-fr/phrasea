<?php

declare(strict_types=1);

namespace App\Border\Consumer\Handler;

use App\Border\BorderManager;
use App\Border\Model\InputFile;
use App\Consumer\Handler\File\NewAssetFromBorderHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use GuzzleHttp\Client;

class FileEntranceHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'file_entrance';

    private BorderManager $borderManager;
    private EventProducer $eventProducer;
    private Client $client;

    public function __construct(BorderManager $borderManager, EventProducer $eventProducer, Client $client)
    {
        $this->borderManager = $borderManager;
        $this->eventProducer = $eventProducer;
        $this->client = $client;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();

        $response = $this->client
            ->get(sprintf('%s/assets/%s', $payload['baseUrl'], $payload['assetId']), [
                'headers' => [
                    'Authorization' => 'AssetToken '.$payload['token'],
                ]
            ]);

        $json = \GuzzleHttp\json_decode($response->getBody()->getContents(), true);

        $file = new InputFile($json['originalName'], $json['mimeType'], $json['size']);

        if ($this->borderManager->acceptFile($file)) {
            $this->eventProducer->publish(new EventMessage(NewAssetFromBorderHandler::EVENT, [
                // TODO send event to Core to create Asset
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
