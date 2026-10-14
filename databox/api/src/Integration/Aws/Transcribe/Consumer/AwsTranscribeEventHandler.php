<?php

declare(strict_types=1);

namespace App\Integration\Aws\Transcribe\Consumer;

use App\Integration\Aws\Transcribe\AwsTranscribeIntegration;
use App\Integration\IntegrationDataManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class AwsTranscribeEventHandler
{
    final public const string EVENT = 'aws_transcribe.event';
    final public const string DATA_EVENT_MESSAGE = 'event_message';

    public function __construct(
        private IntegrationDataManager $integrationDataManager,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(AwsTranscribeEvent $message): void
    {
        $body = $message->getBody();
        $workspaceIntegration = $this->integrationDataManager->getWorkspaceIntegration($message->getIntegrationId());
        // The endpoint is public: only events addressed to an AWS Transcribe integration are accepted
        if (AwsTranscribeIntegration::getName() !== $workspaceIntegration->getIntegration()) {
            return;
        }

        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $this->integrationDataManager->storeData(
            $workspaceIntegration,
            null,
            null,
            self::DATA_EVENT_MESSAGE,
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        if ('SubscriptionConfirmation' === $payload['Type']) {
            $subscribeUrl = $payload['SubscribeURL'] ?? null;
            // The confirmation URL is requested by the worker: never anything but AWS SNS
            if (is_string($subscribeUrl) && self::isSnsUrl($subscribeUrl)) {
                $this->bus->dispatch(new ConfirmSnsSubscription($subscribeUrl));
            }
        }

        if ('Notification' === $payload['Type']) {
            $msg = json_decode((string) $payload['Message'], true, 512, JSON_THROW_ON_ERROR);

            if ('aws.transcribe' === $msg['source']) {
                $this->bus->dispatch(new TranscribeJobStatusChanged(
                    $workspaceIntegration->getId(),
                    $msg
                ));
            }
        }
    }

    public static function isSnsUrl(string $url): bool
    {
        $parts = parse_url($url);

        return 'https' === ($parts['scheme'] ?? null)
            && !isset($parts['user']) && !isset($parts['port'])
            && 1 === preg_match('#^sns\.[a-z0-9-]+\.amazonaws\.com(\.cn)?$#', $parts['host'] ?? '');
    }
}
