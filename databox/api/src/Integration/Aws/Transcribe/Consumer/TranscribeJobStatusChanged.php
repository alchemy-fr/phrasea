<?php

namespace App\Integration\Aws\Transcribe\Consumer;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p2')]
final readonly class TranscribeJobStatusChanged
{
    public function __construct(private string $integrationId, private array $message)
    {
    }

    public function getIntegrationId(): string
    {
        return $this->integrationId;
    }

    public function getMessage(): array
    {
        return $this->message;
    }
}
