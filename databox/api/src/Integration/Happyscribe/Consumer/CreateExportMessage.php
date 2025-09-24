<?php

declare(strict_types=1);

namespace App\Integration\Happyscribe\Consumer;

final readonly class CreateExportMessage
{
    public function __construct(private string $transcriptionId, private string $config)
    {
    }

    public function getTranscriptionId(): string
    {
        return $this->transcriptionId;
    }

    public function getConfig(): string
    {
        return $this->config;
    }
}
