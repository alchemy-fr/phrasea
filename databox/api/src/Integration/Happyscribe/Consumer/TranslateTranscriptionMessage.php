<?php

declare(strict_types=1);

namespace App\Integration\Happyscribe\Consumer;

final readonly class TranslateTranscriptionMessage
{
    public function __construct(private string $translateId, private string $integrationId, private string $assetId, private ?string $locale, private int $retry = 0)
    {
    }

    public function getTranslateId(): string
    {
        return $this->translateId;
    }

    public function getIntegrationId(): string
    {
        return $this->integrationId;
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function getRetry(): int
    {
        return $this->retry;
    }
}
