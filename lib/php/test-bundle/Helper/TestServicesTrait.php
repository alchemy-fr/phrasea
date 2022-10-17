<?php

declare(strict_types=1);

namespace Alchemy\TestBundle\Helper;

use App\Tests\Mock\EventProducerMock;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\EventConsumer;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use PhpAmqpLib\Message\AMQPMessage;

trait TestServicesTrait
{
    /**
     * @template T
     *
     * @param class-string<T> $name
     *
     * @return T
     */
    public static function getService(string $name): object
    {
        return self::getContainer()->get($name);
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return self::getService(EntityManagerInterface::class);
    }

    public function getEventProducer(bool $intercept = false): EventProducerMock
    {
        /** @var EventProducerMock $eventProducer */
        $eventProducer = self::getService(EventProducer::class);

        if ($intercept) {
            $eventProducer->interceptEvents();
        }

        return $eventProducer;
    }

    private function consumeEvent(EventMessage $eventMessage): void
    {
        /** @var EventConsumer $eventConsumer */
        $eventConsumer = self::getService(EventConsumer::class);

        $message = new AMQPMessage($eventMessage->toJson());
        $eventConsumer->processMessage($message);
    }
}
