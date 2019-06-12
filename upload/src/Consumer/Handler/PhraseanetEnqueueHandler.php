<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractLogHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use GuzzleHttp\Client;

class PhraseanetEnqueueHandler extends AbstractLogHandler
{
    const EVENT = 'enqueue_phraseanet';

    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $phraseanetAccessToken;

    public function __construct(
        Client $client,
        string $phraseanetAccessToken
    ) {
        $this->client = $client;
        $this->phraseanetAccessToken = $phraseanetAccessToken;
    }

    public function handle(EventMessage $message): void
    {
        if ('avoid' === $this->phraseanetAccessToken) {
            return;
        }

        $payload = $message->getPayload();

        $this->client->post('/api/v1/upload/enqueue/', [
            'headers' => [
                'Authorization' => 'OAuth '.$this->phraseanetAccessToken,
            ],
            'json' => [
                'assets' => $payload['files'],
                'publisher' => $payload['user_id'],
            ],
        ]);
    }



    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }

    public static function getQueueName(): string
    {
        return 'enqueue_phraseanet';
    }
}
