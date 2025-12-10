<?php

declare(strict_types=1);

namespace App\Integration\Happyscribe\Consumer;

final readonly class CreateExportMessage
{
    public function __construct(private string $transcriptionId, private string $integrationId, private string $assetId, private ?string $locale)
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

    public function getLocale(): ?string
    {
        return $this->locale;
    }
}
