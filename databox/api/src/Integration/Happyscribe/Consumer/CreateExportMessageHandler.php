<?php

declare(strict_types=1);

namespace App\Integration\Happyscribe\Consumer;

use App\Integration\IntegrationManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final readonly class CreateExportMessageHandler
{
    public function __construct(
        private MessageBusInterface $bus,
        private HttpClientInterface $happyscribeClient,
        private IntegrationManager $integrationManager,
    ) {
    }

    public function __invoke(CreateExportMessage $message): void
    {
        $transcriptionId = $message->getTranscriptionId();
        $integrationId = $message->getIntegrationId();

        $integration = $this->integrationManager->loadIntegration($integrationId) ?? throw new \InvalidArgumentException('Integration not found: '.$integrationId);

        $integrationConfig = $this->integrationManager->getIntegrationConfiguration($integration);

        $happyscribeToken = $integrationConfig['apiKey'];

        $resExport = $this->happyscribeClient->request('POST', 'https://www.happyscribe.com/api/v1/exports', [
            'headers' => [
                'Authorization' => 'Bearer '.$happyscribeToken,
            ],
            'json' => [
                'export' => [
                    'format' => $integrationConfig['transcriptFormat'],
                    'transcription_ids' => [
                        $transcriptionId,
                    ],
                ],
            ],
        ]);

        if (200 !== $resExport->getStatusCode()) {
            throw new \RuntimeException('Error when creating transcript export, response status: '.$resExport->getStatusCode());
        }

        $resExportBody = $resExport->toArray();

        $this->bus->dispatch(new ExportTranscriptionMessage($resExportBody['id'], $integrationId, $message->getAssetId(), $message->getLocale()), [new DelayStamp(3 * 1000)]);
    }
}
