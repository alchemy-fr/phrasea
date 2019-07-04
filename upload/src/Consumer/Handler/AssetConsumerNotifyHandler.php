<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractLogHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use GuzzleHttp\Client;

class AssetConsumerNotifyHandler extends AbstractLogHandler
{
    const EVENT = 'asset_consumer_notify';

    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $targetUri;

    /**
     * @var string
     */
    private $targetAccessToken;
    /**
     * @var string
     */
    private $uploadBaseUrl;

    public function __construct(
        Client $client,
        string $targetUri,
        string $targetAccessToken,
        string $uploadBaseUrl
    ) {
        $this->client = $client;
        $this->targetUri = $targetUri;
        $this->targetAccessToken = $targetAccessToken;
        $this->uploadBaseUrl = $uploadBaseUrl;
    }

    public function handle(EventMessage $message): void
    {
        if ('avoid' === $this->targetAccessToken) {
            return;
        }

        $payload = $message->getPayload();

        $this->client->post($this->targetUri, [
            'headers' => [
                'Authorization' => 'OAuth '.$this->targetAccessToken,
            ],
            'json' => [
                'assets' => $payload['files'],
                'publisher' => $payload['user_id'],
                'token' => $payload['token'],
                'base_url' => $this->uploadBaseUrl,
            ],
        ]);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }

    public static function getQueueName(): string
    {
        return 'asset_consumer_notify';
    }
}
