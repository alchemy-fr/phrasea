<?php

declare(strict_types=1);

namespace App\Tests;

use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use InvalidArgumentException;

class EventProducerMock extends EventProducer
{
    private array $events = [];

    public function __construct()
    {
    }

    public function publish(
        EventMessage $message,
        string $deprecatedRoutingKey = null,
        array $deprecatedProperties = [],
        ?array $deprecatedHeaders = null
    ): void {
        $this->events[] = $message;
    }

    public function shiftEvent(): EventMessage
    {
        if (empty($this->events)) {
            throw new InvalidArgumentException('No events were triggered');
        }

        return array_shift($this->events);
    }
}
