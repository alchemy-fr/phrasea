<?php

namespace App\Consumer\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

readonly class QuietContextStamp implements StampInterface
{
    public function __construct(
        private bool $noWebhook = false,
        private bool $noNotification = false,
    ) {
    }

    public function isNoWebhook(): bool
    {
        return $this->noWebhook;
    }

    public function isNoNotification(): bool
    {
        return $this->noNotification;
    }
}
