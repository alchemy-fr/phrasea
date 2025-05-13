<?php

declare(strict_types=1);

namespace Alchemy\TestBundle\Helper;

use Alchemy\MessengerBundle\Transport\TestTransport;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

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

    public function interceptMessengerEvents($queueName = 'p1'): InMemoryTransport
    {
        /** @var TestTransport $testTransport */
        $testTransport = self::getService('messenger.transport.'.$queueName);

        return $testTransport->intercept();
    }

    private function consumeEvent(Envelope $envelope, $queueName = 'p1'): void
    {
        /** @var TestTransport $testTransport */
        $testTransport = self::getService('messenger.transport.'.$queueName);
        $syncTransport = $testTransport->getSyncTransport();

        $envelope->withoutStampsOfType(SentStamp::class);

        $syncTransport->send($envelope);
    }
}
