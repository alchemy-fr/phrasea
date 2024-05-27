<?php

declare(strict_types=1);

namespace App\Integration\Aws\Transcribe\Consumer;

use App\Integration\IntegrationDataManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class AwsTranscribeEventHandler
{
    final public const EVENT = 'aws_transcribe.event';
    final public const DATA_EVENT_MESSAGE = 'event_message';

    public function __construct(
        private IntegrationDataManager $integrationDataManager,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(AwsTranscribeEvent $message): void
    {
        $body = $message->getBody();
        $workspaceIntegration = $this->integrationDataManager->getWorkspaceIntegration($message->getIntegrationId());

        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $this->integrationDataManager->storeFileData(
            $workspaceIntegration,
            null,
            self::DATA_EVENT_MESSAGE,
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        if ('SubscriptionConfirmation' === $payload['Type']) {
            $this->bus->dispatch(new ConfirmSnsSubscription($payload['SubscribeURL']));
        }

        if ('Notification' === $payload['Type']) {
            $msg = json_decode($payload['Message'], true, 512, JSON_THROW_ON_ERROR);

            if ('aws.transcribe' === $msg['source']) {
                $this->bus->dispatch(new TranscribeJobStatusChanged(
                    $workspaceIntegration->getId(),
                    $msg
                ));
            }
        }
    }
}
