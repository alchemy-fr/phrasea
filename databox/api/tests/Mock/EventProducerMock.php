<?php

declare(strict_types=1);

namespace App\Tests\Mock;

use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

class EventProducerMock extends EventProducer
{
    private bool $intercept = false;
    private array $events = [];
    private EventProducer $inner;

    public function __construct(EventProducer $inner)
    {
        $this->inner = $inner;
    }

    public function publish(
        EventMessage $message,
        string $deprecatedRoutingKey = null,
        array $deprecatedProperties = [],
        ?array $deprecatedHeaders = null
    ): void {
        $this->events[] = $message;

        if (!$this->intercept) {
            $this->inner->publish($message, $deprecatedRoutingKey, $deprecatedProperties, $deprecatedHeaders);
        }
    }

    public function shiftEvent(): EventMessage
    {
        if (empty($this->events)) {
            throw new \InvalidArgumentException('No events were triggered');
        }

        return array_shift($this->events);
    }

    public function interceptEvents(): void
    {
        $this->intercept = true;
    }
}
