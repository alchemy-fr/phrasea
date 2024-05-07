<?php

namespace App\Integration\Message;

abstract readonly class AbstractFileActionMessage
{
    public function __construct(
        private string $fileId,
        private string $integrationId,
    )
    {
    }

    public function getFileId(): string
    {
        return $this->fileId;
    }

    public function getIntegrationId(): string
    {
        return $this->integrationId;
    }
}
