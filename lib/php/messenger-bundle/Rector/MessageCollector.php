<?php

namespace Alchemy\MessengerBundle\Rector;

final class MessageCollector
{
    static ?self $instance = null;

    private array $messages = [];

    public function addMessage(string $fqn, array $args): void
    {
        $this->messages[$fqn] = $args;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public static function get(): self
    {
        return self::$instance ?? (self::$instance = new self());
    }
}


