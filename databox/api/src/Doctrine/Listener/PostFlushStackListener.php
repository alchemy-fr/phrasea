<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

abstract class PostFlushStackListener implements EventSubscriber
{
    private array $callbacks = [];
    private array $events = [];
    private EventProducer $eventProducer;

    /**
     * @required
     */
    public function setEventProducer(EventProducer $eventProducer)
    {
        $this->eventProducer = $eventProducer;
    }

    public function addCallback(callable $callback): void
    {
        $this->callbacks[] = $callback;
    }

    public function addEvent(EventMessage $eventMessage): void
    {
        $this->events[] = $eventMessage;
    }

    public function clearEvents(): void
    {
        $this->events = [];
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $callbacks = $this->callbacks;
        $events = $this->events;

        $this->callbacks = [];
        $this->events = [];

        while ($callback = array_shift($callbacks)) {
            $callback();
        }

        while ($event = array_shift($events)) {
            $this->eventProducer->publish($event);
        }
    }

    public function getSubscribedEvents()
    {
        return [
            Events::postFlush,
        ];
    }
}
