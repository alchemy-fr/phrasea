<?php

declare(strict_types=1);

namespace App\Integration\Happyscribe\Consumer;

final readonly class TranslateTranscriptionMessage
{
    public function __construct(private string $translateId, private string $config, private int $delay = 5000)
    {
    }

    public function getTranslateId(): string
    {
        return $this->translateId;
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
