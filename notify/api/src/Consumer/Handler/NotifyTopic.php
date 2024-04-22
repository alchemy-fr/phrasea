<?php

namespace App\Consumer\Handler;

final readonly class NotifyTopic
{
    public function __construct(
        private string $topic,
        private string $template,
        private array $parameters = [],

    ) {
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
