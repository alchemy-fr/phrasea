<?php

declare(strict_types=1);

namespace App\Tests\Unit\Admin;

use Alchemy\AdminBundle\Utils\WorkerQueues;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransport;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

class WorkerQueuesTest extends TestCase
{
    public function testListsOnlyAmqpTransportsInDeclarationOrder(): void
    {
        $workerQueues = new WorkerQueues(new ServiceLocator([
            'p1' => fn (): TransportInterface => $this->createStub(AmqpTransport::class),
            'failed' => fn (): TransportInterface => $this->createStub(TransportInterface::class),
            'sync' => fn (): TransportInterface => new SyncTransport($this->createStub(MessageBusInterface::class)),
            'task' => fn (): TransportInterface => $this->createStub(AmqpTransport::class),
        ]));

        $this->assertSame(['p1', 'task'], $workerQueues->getNames());
    }

    public function testReturnsNothingWithoutAmqpTransport(): void
    {
        $workerQueues = new WorkerQueues(new ServiceLocator([
            'failed' => fn (): TransportInterface => $this->createStub(TransportInterface::class),
            'sync' => fn (): TransportInterface => new SyncTransport($this->createStub(MessageBusInterface::class)),
        ]));

        $this->assertSame([], $workerQueues->getNames());
    }
}
