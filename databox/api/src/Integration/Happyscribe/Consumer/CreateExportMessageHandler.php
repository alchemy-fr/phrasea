<?php

declare(strict_types=1);

namespace App\Integration\Happyscribe\Consumer;

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
    ) {
    }

    public function __invoke(CreateExportMessage $message): void
    {
        $transcriptionId = $message->getTranscriptionId();
        $config = json_decode($message->getConfig(), true, 512, JSON_THROW_ON_ERROR);
        $happyscribeToken = $config['apiKey'];

        $resExport = $this->happyscribeClient->request('POST', 'https://www.happyscribe.com/api/v1/exports', [
            'headers' => [
                'Authorization' => 'Bearer '.$happyscribeToken,
            ],
            'json' => [
                'export' => [
                    'format' => $config['transcriptFormat'],
                    'transcription_ids' => [
                        $transcriptionId,
                    ],
                ],
            ],
        ]);

        if (200 !== $resExport->getStatusCode()) {
            throw new \RuntimeException('error when creating transcript export, response status : '.$resExport->getStatusCode());
        }

        $resExportBody = $resExport->toArray();

        $config['exportId'] = $resExportBody['id'];

        $this->bus->dispatch(new ExportTranscriptionMessage($transcriptionId, json_encode($config)), [new DelayStamp(3 * 1000)]);
    }
}
