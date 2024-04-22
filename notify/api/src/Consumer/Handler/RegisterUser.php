<?php

namespace App\Consumer\Handler;

final readonly class RegisterUser
{
    public function __construct(
        private string $userId,
        private array $contactInfo,

    ) {
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getContactInfo(): array
    {
        return $this->contactInfo;
    }
}
