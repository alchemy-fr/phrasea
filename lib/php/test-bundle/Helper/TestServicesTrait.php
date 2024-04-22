<?php

declare(strict_types=1);

namespace Alchemy\TestBundle\Helper;

use Alchemy\MessengerBundle\Transport\TestTransport;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
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

    public function interceptMessengerEvents(): InMemoryTransport
    {
        /** @var TestTransport $testTransport */
        $testTransport = self::getService('messenger.transport.p1');

        return $testTransport->intercept();
    }

    private function consumeEvent(Envelope $envelope): void
    {
        /** @var TestTransport $testTransport */
        $testTransport = self::getService('messenger.transport.p1');
        $syncTransport = $testTransport->getSyncTransport();

        $envelope->withoutStampsOfType(SentStamp::class);

        $syncTransport->send($envelope);
    }
}
