<?php

declare(strict_types=1);

namespace App\Integration\Aws\Transcribe\Consumer;

use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use GuzzleHttp\Client;

class ConfirmSnsSubscriptionHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'aws_transcribe.confirm_sns_subscription';

    public function __construct(private readonly Client $integrationClient)
    {
    }

    public function handle(EventMessage $message): void
    {
        $url = $message->getPayload()['url'];

        $this->integrationClient->request('GET', $url);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }

    public static function createEvent(string $url): EventMessage
    {
        return new EventMessage(self::EVENT, [
            'url' => $url,
        ]);
    }
}
