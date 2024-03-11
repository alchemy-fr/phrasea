<?php

declare(strict_types=1);

namespace Alchemy\MessengerBundle\Listener;

use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsEventListener(KernelEvents::TERMINATE, 'onTerminate')]
#[AsEventListener(ConsoleEvents::TERMINATE, 'onTerminate')]
final class TerminateStackListener
{
    private array $callbacks = [];
    private array $messages = [];
    private array $events = [];

    private EventProducer $eventProducer;

    #[Required]
    public function setEventProducer(EventProducer $eventProducer)
    {
        $this->eventProducer = $eventProducer;
    }

    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function rollback(): void
    {
        $this->callbacks = [];
        $this->messages = [];
        $this->events = [];
    }

    public function addCallback(callable $callback): void
    {
        $this->callbacks[] = $callback;
    }

    /**
     * @deprecated Use addBusMessage instead.
     */
    public function addEvent(EventMessage $eventMessage): void
    {
        $this->events[] = $eventMessage;
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

        $events = $this->events;
        $this->events = [];
        while ($event = array_shift($events)) {
            $this->eventProducer->publish($event);
        }
    }
}
