<?php

declare(strict_types=1);

namespace App\Integration\Happyscribe\Consumer;

use App\Integration\IntegrationManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final readonly class TranslateTranscriptionMessageHandler
{
    public function __construct(
        private MessageBusInterface $bus,
        private HttpClientInterface $happyscribeClient,
        private IntegrationManager $integrationManager,
    ) {
    }

    public function __invoke(TranslateTranscriptionMessage $message): void
    {
        $failureTranslateMessage = '';
        $translatedTranscriptionId = '';

        $integrationId = $message->getIntegrationId();
        $translateId = $message->getTranslateId();

        $integration = $this->integrationManager->loadIntegration($integrationId) ?? throw new \RuntimeException('Integration not found: '.$integrationId);

        $integrationConfig = $this->integrationManager->getIntegrationConfiguration($integration);

        $happyscribeToken = $integrationConfig['apiKey'];

        $resCheckTranslate = $this->happyscribeClient->request('GET', 'https://www.happyscribe.com/api/v1/task/transcription_translation/'.$translateId, [
            'headers' => [
                'Authorization' => 'Bearer '.$happyscribeToken,
            ],
        ]);

        if (200 !== $resCheckTranslate->getStatusCode()) {
            throw new \RuntimeException('Error when checking translation task ,response status : '.$resCheckTranslate->getStatusCode());
        }

        $resCheckTranslateBody = $resCheckTranslate->toArray();

        $checkTranslateStatus = $resCheckTranslateBody['state'];
        if (isset($resCheckTranslateBody['failureReason'])) {
            $failureTranslateMessage = $resCheckTranslateBody['failureReason'];
        }

        if ('done' == $checkTranslateStatus) {
            $translatedTranscriptionId = $resCheckTranslateBody['translatedTranscriptionId'];
        }

        if (!in_array($checkTranslateStatus, ['done', 'failed'])) {
            $retryNumber = $message->getRetry();
            $delays = [3, 5, 10, 30, 60, 120];
            $delay = $delays[$retryNumber] ?? 200;

            $delay = $delay * 1000;

            $this->bus->dispatch(new TranslateTranscriptionMessage($translateId, $integrationId, $message->getAssetId(), $message->getLocale(), $retryNumber + 1), [new DelayStamp($delay)]);

            return;
        }

        if ('done' != $checkTranslateStatus) {
            throw new \RuntimeException('Error when translate: '.$failureTranslateMessage);
        }

        $this->bus->dispatch(new CreateExportMessage($translatedTranscriptionId, $integrationId, $message->getAssetId(), $message->getLocale()));
    }
}
