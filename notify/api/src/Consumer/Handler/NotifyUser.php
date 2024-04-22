<?php

namespace App\Consumer\Handler;

final readonly class NotifyUser
{
    public function __construct(
        private string $userId,
        private string $template,
        private array $parameters = [],
        private ?array $contactInfo = null,
    ) {
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getContactInfo(): ?array
    {
        return $this->contactInfo;
    }
}
