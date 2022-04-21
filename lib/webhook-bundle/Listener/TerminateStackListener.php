<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Listener;

use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

class TerminateStackListener
{
    private array $events = [];
    private EventProducer $eventProducer;

    /**
     * @required
     */
    public function setEventProducer(EventProducer $eventProducer)
    {
        $this->eventProducer = $eventProducer;
    }

    public function addEvent(EventMessage $eventMessage): void
    {
        $this->events[] = $eventMessage;
    }

    public function onTerminate(): void
    {
        $events = $this->events;

        $this->events = [];
        while ($event = array_shift($events)) {
            $this->eventProducer->publish($event);
        }
    }
}
