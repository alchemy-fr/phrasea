<?php

namespace App\Integration\Aws\Transcribe\Consumer;

final readonly class ConfirmSnsSubscription
{
    public function __construct(private string $url)
    {
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
