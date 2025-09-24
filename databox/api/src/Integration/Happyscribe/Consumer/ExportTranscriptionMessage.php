<?php

declare(strict_types=1);

namespace App\Integration\Happyscribe\Consumer;

final readonly class ExportTranscriptionMessage
{
    public function __construct(private string $transcriptionId, private string $config, private int $delay = 3000)
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

    public function getDelay(): int
    {
        return $this->delay;
    }
}
