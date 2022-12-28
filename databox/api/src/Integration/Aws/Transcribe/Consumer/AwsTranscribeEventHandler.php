<?php

declare(strict_types=1);

namespace App\Integration\Aws\Transcribe\Consumer;

use App\Integration\IntegrationDataManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

class AwsTranscribeEventHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'aws_transcribe.event';
    public const DATA_EVENT_MESSAGE = 'event_message';
    private IntegrationDataManager $integrationDataManager;
    private EventProducer $eventProducer;

    public function __construct(IntegrationDataManager $integrationDataManager, EventProducer $eventProducer)
    {
        $this->integrationDataManager = $integrationDataManager;
        $this->eventProducer = $eventProducer;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $body = $payload['body'];
        $workspaceIntegration = $this->integrationDataManager->getWorkspaceIntegration($payload['integrationId']);

        $payload = \GuzzleHttp\json_decode($body, true);

        $this->integrationDataManager->storeData(
            $workspaceIntegration,
            null,
            self::DATA_EVENT_MESSAGE,
            \GuzzleHttp\json_encode($payload)
        );

        if ('SubscriptionConfirmation' === $payload['Type']) {
            $this->eventProducer->publish(ConfirmSnsSubscriptionHandler::createEvent($payload['SubscribeURL']));
        }

        if ('Notification' === $payload['Type']) {
            $message = \GuzzleHttp\json_decode($payload['Message'], true);

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
