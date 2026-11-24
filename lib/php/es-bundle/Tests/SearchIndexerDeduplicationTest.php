<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Tests;

use Alchemy\ESBundle\Indexer\IndexPersister;
use Alchemy\ESBundle\Indexer\Operation;
use Alchemy\ESBundle\Indexer\SearchIndexer;
use Alchemy\ESBundle\Message\ESIndex;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;

class SearchIndexerDeduplicationTest extends TestCase
{
    /**
     * @var object[]
     */
    private array $dispatched = [];

    protected function setUp(): void
    {
        $this->dispatched = [];
    }

    public function testIdenticalMessagesShareTheirDeduplicationKey(): void
    {
        $indexer = $this->createIndexer(120);
        $indexer->scheduleObjectsIndex(A::class, ['1', '2'], Operation::Upsert);
        $indexer->scheduleObjectsIndex(A::class, ['1', '2'], Operation::Upsert);
        $indexer->scheduleObjectsIndex(A::class, ['1', '3'], Operation::Upsert);

        $this->assertCount(3, $this->dispatched);
        $stamps = array_map($this->getStamp(...), $this->dispatched);

        foreach ($this->dispatched as $envelope) {
            $this->assertInstanceOf(ESIndex::class, $envelope->getMessage());
        }
        foreach ($stamps as $stamp) {
            $this->assertTrue($stamp->onlyDeduplicateInQueue());
            $this->assertSame(120.0, $stamp->getTtl());
            $this->assertStringStartsWith('dedup:'.ESIndex::class.':', (string) $stamp->getKey());
        }

        $this->assertSame((string) $stamps[0]->getKey(), (string) $stamps[1]->getKey());
        $this->assertNotSame((string) $stamps[0]->getKey(), (string) $stamps[2]->getKey());
    }

    public function testNoDeduplicationWithoutTtl(): void
    {
        $this->createIndexer(0)->scheduleObjectsIndex(A::class, ['1'], Operation::Upsert);

        $this->assertCount(1, $this->dispatched);
        $this->assertInstanceOf(ESIndex::class, $this->dispatched[0]);
    }

    private function getStamp(object $envelope): DeduplicateStamp
    {
        $this->assertInstanceOf(Envelope::class, $envelope);
        $stamps = $envelope->all(DeduplicateStamp::class);
        $this->assertCount(1, $stamps);

        return $stamps[0];
    }

    private function createIndexer(float $deduplicationTtl): SearchIndexer
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message): Envelope {
            $this->dispatched[] = $message;

            return Envelope::wrap($message);
        });

        return new SearchIndexer(
            $bus,
            $this->createStub(EntityManagerInterface::class),
            new NullLogger(),
            new IndexPersister([]),
            [],
            false,
            deduplicationTtl: $deduplicationTtl,
        );
    }
}
