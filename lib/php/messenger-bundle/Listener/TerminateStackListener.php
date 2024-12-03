<?php

declare(strict_types=1);

namespace Alchemy\MessengerBundle\Listener;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener(KernelEvents::TERMINATE, 'onTerminate')]
#[AsEventListener(ConsoleEvents::TERMINATE, 'onTerminate')]
#[AsEventListener(WorkerMessageHandledEvent::class, method: 'onTerminate')]
final class TerminateStackListener
{
    private array $callbacks = [];
    private array $messages = [];
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function rollback(): void
    {
        $this->callbacks = [];
        $this->messages = [];
    }

    public function addCallback(callable $callback): void
    {
        $this->callbacks[] = $callback;
    }

    public function addBusMessage(object $message): void
    {
        $this->messages[] = $message;
    }

    public function onTerminate(): void
    {
        $callbacks = $this->callbacks;

        $this->callbacks = [];
        while ($callback = array_shift($callbacks)) {
            $callback();
        }

        $messages = $this->messages;
        $this->messages = [];
        while ($message = array_shift($messages)) {
            $this->bus->dispatch($message);
        }
    }
}
