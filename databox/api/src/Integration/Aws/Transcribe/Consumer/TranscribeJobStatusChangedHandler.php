<?php

declare(strict_types=1);

namespace App\Integration\Aws\Transcribe\Consumer;

use App\Integration\IntegrationManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TranscribeJobStatusChangedHandler
{
    public function __construct(private IntegrationManager $integrationManager)
    {
    }

    public function __invoke(TranscribeJobStatusChanged $message): void
    {
        $msg = $message->getMessage();
        $detail = $msg['detail'];

        if ('COMPLETED' === $detail['TranscriptionJobStatus']) {
            $this->integrationManager->callIntegrationFunction($message->getIntegrationId(), 'handlePostComplete', [
                'message' => $msg,
            ]);
        }
    }
}
