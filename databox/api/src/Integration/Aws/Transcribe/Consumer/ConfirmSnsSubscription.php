<?php

namespace App\Integration\Aws\Transcribe\Consumer;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p2')]
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
