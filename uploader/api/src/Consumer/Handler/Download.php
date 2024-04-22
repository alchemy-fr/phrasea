<?php

namespace App\Consumer\Handler;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p2')]
final readonly class Download
{
    public function __construct(
        private string $url,
        private string $userId,
        private string $targetId,
        private array $formData,
        private string $locale,
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getTargetId(): string
    {
        return $this->targetId;
    }

    public function getFormData(): array
    {
        return $this->formData;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
