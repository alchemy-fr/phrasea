<?php

namespace App\Integration\Aws\Transcribe\Consumer;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p2')]
final readonly class AwsTranscribeEvent
{
    public function __construct(private string $integrationId, private string $body)
    {
    }

    public function getIntegrationId(): string
    {
        return $this->integrationId;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
