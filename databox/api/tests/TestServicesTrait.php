<?php

declare(strict_types=1);

namespace App\Tests;

use Arthem\Bundle\RabbitBundle\Consumer\EventConsumer;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use PhpAmqpLib\Message\AMQPMessage;

trait TestServicesTrait
{
    /**
     * @template T
     *
     * @param class-string<T> $name
     * @return T
     */
    public static function getService(string $name): object
    {
        return self::getContainer()->get($name);
    }

    private function consumeEvent(EventMessage $eventMessage)
    {
        /** @var EventConsumer $eventConsumer */
        $eventConsumer = self::getService(EventConsumer::class);

        $message = new AMQPMessage($eventMessage->toJson());
        $eventConsumer->processMessage($message);
    }
}
