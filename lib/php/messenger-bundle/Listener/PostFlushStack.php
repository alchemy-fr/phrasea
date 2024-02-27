<?php

declare(strict_types=1);

namespace Alchemy\MessengerBundle\Listener;

use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsDoctrineListener(Events::postFlush)]
final class PostFlushStack
{
    private array $callbacks = [];
    private array $messages = [];
    private array $events = [];

    private EventProducer $eventProducer;

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly TerminateStackListener $terminateStackListener,
    ) {
    }

    #[Required]
    public function setEventProducer(EventProducer $eventProducer)
    {
        $this->eventProducer = $eventProducer;
    }

    public function addCallback(callable $callback): void
    {
        $this->callbacks[] = $callback;
    }

    public function addBusMessage(object $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * @deprecated Use addBusMessage instead.
     */
    public function addEvent(EventMessage $eventMessage): void
    {
        $this->events[] = $eventMessage;
    }

    public function rollback(): void
    {
        $this->callbacks = [];
        $this->messages = [];
        $this->terminateStackListener->rollback();
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $callbacks = $this->callbacks;
        $this->callbacks = [];
        $messages = $this->messages;
        $this->messages = [];
        $events = $this->events;
        $this->events = [];

        $em = $args->getObjectManager();
        if ($em->getConnection()->getTransactionNestingLevel() > 0) {
            while ($callback = array_shift($callbacks)) {
                $this->terminateStackListener->addCallback($callback);
            }

            while ($message = array_shift($messages)) {
                $this->terminateStackListener->addBusMessage($message);
            }

            return;
        }

        while ($callback = array_shift($callbacks)) {
            $callback();
        }

        while ($event = array_shift($events)) {
            $this->eventProducer->publish($event);
        }

        while ($message = array_shift($messages)) {
            $this->bus->dispatch($message);
        }

        while ($event = array_shift($events)) {
            $this->eventProducer->publish($event);
        }
    }
}
