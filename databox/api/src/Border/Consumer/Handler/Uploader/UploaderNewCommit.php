<?php

namespace App\Border\Consumer\Handler\Uploader;

final readonly class UploaderNewCommit
{
    public function __construct(
        private array $payload
    ) {
    }

    public function getPayload(): array
    {
        return $this->payload;
    }
}
