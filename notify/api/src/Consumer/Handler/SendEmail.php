<?php

namespace App\Consumer\Handler;

final readonly class SendEmail
{
    public function __construct(
        private string $email,
        private string $template,
        private array $parameters = [],
        private ?string $locale = null,
    ) {
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
