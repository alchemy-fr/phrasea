<?php

namespace App\Border\Consumer\Handler\Uploader;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class UploaderNewCommit
{
    public function __construct(
        private array $payload,
    ) {
    }

    public function getPayload(): array
    {
        return $this->payload;
    }
}
