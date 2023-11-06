<?php

declare(strict_types=1);

namespace App\Integration\Aws\Transcribe\Consumer;

use App\Integration\IntegrationDataManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

class AwsTranscribeEventHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'aws_transcribe.event';
    final public const DATA_EVENT_MESSAGE = 'event_message';

    public function __construct(private readonly IntegrationDataManager $integrationDataManager, private readonly EventProducer $eventProducer)
    {
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $body = $payload['body'];
        $workspaceIntegration = $this->integrationDataManager->getWorkspaceIntegration($payload['integrationId']);

        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $this->integrationDataManager->storeData(
            $workspaceIntegration,
            null,
            self::DATA_EVENT_MESSAGE,
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        if ('SubscriptionConfirmation' === $payload['Type']) {
            $this->eventProducer->publish(ConfirmSnsSubscriptionHandler::createEvent($payload['SubscribeURL']));
        }

        if ('Notification' === $payload['Type']) {
            $message = json_decode($payload['Message'], true, 512, JSON_THROW_ON_ERROR);

            if ('aws.transcribe' === $message['source']) {
                $this->eventProducer->publish(TranscribeJobStatusChangedHandler::createEvent(
                    $workspaceIntegration->getId(),
                    $message
                ));
            }
        }
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }

    public static function createEvent(string $integrationId, string $body): EventMessage
    {
        return new EventMessage(self::EVENT, [
            'integrationId' => $integrationId,
            'body' => $body,
        ]);
    }
}
