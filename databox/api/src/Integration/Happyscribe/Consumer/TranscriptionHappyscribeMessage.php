<?php

declare(strict_types=1);

namespace App\Integration\Happyscribe\Consumer;

final readonly class TranscriptionHappyscribeMessage
{
    public function __construct(private string $transcriptionId, private string $integrationId, private string $assetId, private ?string $sourceLanguage, private int $retry = 0)
    {
    }

    public function getTranscriptionId(): string
    {
        return $this->transcriptionId;
    }

    public function getIntegrationId(): string
    {
        return $this->integrationId;
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function getSourceLanguage(): ?string
    {
        return $this->sourceLanguage;
    }

    public function getRetry(): int
    {
        return $this->retry;
    }
}
