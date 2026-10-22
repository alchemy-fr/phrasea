<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Tests;

use Alchemy\ESBundle\Indexer\IndexPersister;
use Alchemy\ESBundle\Indexer\Operation;
use Alchemy\ESBundle\Indexer\SearchIndexer;
use Alchemy\ESBundle\Listener\DeferredIndexListener;
use Alchemy\ESBundle\Message\ESIndex;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class DeferredIndexListenerTest extends TestCase
{
    private DeferredIndexListener $listener;
    private EntityManagerInterface $em;
    private object $connection;

    /**
     * @var ESIndex[]
     */
    private array $dispatched = [];

    protected function setUp(): void
    {
        A::reset();
        $this->dispatched = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message): Envelope {
            $this->dispatched[] = $message;

            return new Envelope($message);
        });
        $this->em = $this->createStub(EntityManagerInterface::class);
        $this->connection = new \stdClass();

        $this->listener = new DeferredIndexListener(new SearchIndexer(
            $bus,
            $this->em,
            new NullLogger(),
            new IndexPersister([]),
            [],
            false,
        ));
    }

    public function testRollbackDiscardsWhatTheTransactionScheduledOnly(): void
    {
        $before = new A([]);
        $during = new A([]);

        $this->persist($before);
        $this->listener->onTransactionBegin($this->connection);
        $this->persist($during);
        $this->listener->onTransactionRollBack($this->connection);

        $this->assertFlushed([A::class => [Operation::Insert->value => ['A1']]]);
    }

    public function testRollbackRestoresUpdatesAndDeletions(): void
    {
        $updated = new A([]);
        $removed = new A([]);

        $this->update($updated);
        $this->listener->onTransactionBegin($this->connection);
        $this->remove($updated);
        $this->remove($removed);
        $this->listener->onTransactionRollBack($this->connection);

        $this->assertFlushed([A::class => [Operation::Upsert->value => ['A1']]]);
    }

    public function testCommittedTransactionIsNotDiscardedByALaterRollback(): void
    {
        $committed = new A([]);
        $rolledBack = new A([]);

        $this->listener->onTransactionBegin($this->connection);
        $this->persist($committed);
        $this->listener->onTransactionCommit($this->connection);

        $this->listener->onTransactionBegin($this->connection);
        $this->persist($rolledBack);
        $this->listener->onTransactionRollBack($this->connection);

        $this->assertFlushed([A::class => [Operation::Insert->value => ['A1']]]);
    }

    public function testRollbackWithoutTransactionKeepsTheQueues(): void
    {
        $this->listener->onTransactionBegin($this->connection);
        $this->listener->onTransactionCommit($this->connection);
        $this->persist(new A([]));
        $this->listener->onTransactionRollBack($this->connection);

        $this->assertFlushed([A::class => [Operation::Insert->value => ['A1']]]);
    }

    public function testRollbackOnAConnectionOnlyRestoresItsOwnSnapshot(): void
    {
        $other = new \stdClass();

        $this->persist(new A([]));
        $this->listener->onTransactionBegin($other);
        $this->persist(new A([]));
        $this->listener->onTransactionCommit($other);
        $this->listener->onTransactionRollBack($this->connection);

        $this->assertFlushed([A::class => [Operation::Insert->value => ['A1', 'A2']]]);
    }

    public function testFlushEmptiesTheQueues(): void
    {
        $this->persist(new A([]));
        $this->assertFlushed([A::class => [Operation::Insert->value => ['A1']]]);

        $this->listener->flush();
        $this->assertSame([], $this->dispatched);
    }

    private function persist(object $entity): void
    {
        $this->listener->postPersist(new PostPersistEventArgs($entity, $this->em));
    }

    private function update(object $entity): void
    {
        $this->listener->postUpdate(new PostUpdateEventArgs($entity, $this->em));
    }

    private function remove(object $entity): void
    {
        $this->listener->preRemove(new PreRemoveEventArgs($entity, $this->em));
    }

    private function assertFlushed(array $expected): void
    {
        $this->listener->flush();
        $this->assertCount(1, $this->dispatched);
        $this->assertInstanceOf(ESIndex::class, $this->dispatched[0]);
        $this->assertSame($expected, $this->dispatched[0]->getObjects());
        $this->dispatched = [];
    }
}
