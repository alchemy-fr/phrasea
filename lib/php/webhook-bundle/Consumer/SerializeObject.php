<?php

namespace Alchemy\WebhookBundle\Consumer;

final readonly class SerializeObject
{
    public function __construct(
        private string $class,
        private string $event,
        private array $data,
        private ?array $changeSet = null
    ) {
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getChangeSet(): ?array
    {
        return $this->changeSet;
    }
}
