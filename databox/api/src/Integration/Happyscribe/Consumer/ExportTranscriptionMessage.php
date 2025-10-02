<?php

declare(strict_types=1);

namespace App\Integration\Happyscribe\Consumer;

final readonly class ExportTranscriptionMessage
{
    public function __construct(private string $exportId, private string $integrationId, private string $assetId, private ?string $locale, private int $retry = 0)
    {
    }

    public function getExportId(): string
    {
        return $this->exportId;
    }

    public function getIntegrationId(): string
    {
        return $this->integrationId;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function getRetry(): int
    {
        return $this->retry;
    }
}
