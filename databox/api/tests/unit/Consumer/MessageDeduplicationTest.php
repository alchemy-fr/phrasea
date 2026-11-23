<?php

declare(strict_types=1);

namespace App\Tests\Unit\Consumer;

use App\Consumer\Handler\Search\IndexAllAssets;
use App\Consumer\Handler\Search\IndexAllCollections;
use App\Consumer\Handler\Search\IndexCollectionBranch;
use App\Consumer\MessageDeduplication;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;

class MessageDeduplicationTest extends TestCase
{
    public function testSamePayloadGivesTheSameKey(): void
    {
        $this->assertSame(
            $this->getKey(MessageDeduplication::coalesce(new IndexAllAssets('ws-1'))),
            $this->getKey(MessageDeduplication::coalesce(new IndexAllAssets('ws-1'))),
        );
        $this->assertSame(
            $this->getKey(MessageDeduplication::coalesce(new IndexAllAssets())),
            $this->getKey(MessageDeduplication::untilHandled(new IndexAllAssets())),
        );
    }

    public function testDifferentPayloadsGiveDifferentKeys(): void
    {
        $this->assertNotSame(
            $this->getKey(MessageDeduplication::coalesce(new IndexAllAssets('ws-1'))),
            $this->getKey(MessageDeduplication::coalesce(new IndexAllAssets('ws-2'))),
        );
        $this->assertNotSame(
            $this->getKey(MessageDeduplication::coalesce(new IndexAllAssets())),
            $this->getKey(MessageDeduplication::coalesce(new IndexAllAssets('ws-1'))),
        );
        $this->assertNotSame(
            $this->getKey(MessageDeduplication::coalesce(new IndexCollectionBranch('c-1', true))),
            $this->getKey(MessageDeduplication::coalesce(new IndexCollectionBranch('c-1', false))),
        );
    }

    public function testDifferentClassesWithTheSamePayloadGiveDifferentKeys(): void
    {
        $this->assertNotSame(
            $this->getKey(MessageDeduplication::coalesce(new IndexAllAssets('ws-1'))),
            $this->getKey(MessageDeduplication::coalesce(new IndexAllCollections('ws-1'))),
        );
    }

    public function testCoalesceReleasesTheLockOnReception(): void
    {
        $message = new IndexAllAssets('ws-1');
        $envelope = MessageDeduplication::coalesce($message, 120);

        $this->assertSame($message, $envelope->getMessage());
        $stamp = $this->getStamp($envelope);
        $this->assertTrue($stamp->onlyDeduplicateInQueue());
        $this->assertSame(120.0, $stamp->getTtl());
        $this->assertStringStartsWith('dedup:'.IndexAllAssets::class.':', $this->getKey($envelope));

        $this->assertSame(3600.0, $this->getStamp(MessageDeduplication::coalesce($message))->getTtl());
    }

    public function testUntilHandledKeepsTheLockUntilTheMessageIsHandled(): void
    {
        $stamp = $this->getStamp(MessageDeduplication::untilHandled(new IndexAllAssets('ws-1')));

        $this->assertFalse($stamp->onlyDeduplicateInQueue());
        $this->assertSame(900.0, $stamp->getTtl());
    }

    private function getStamp(Envelope $envelope): DeduplicateStamp
    {
        $stamps = $envelope->all(DeduplicateStamp::class);
        $this->assertCount(1, $stamps);

        return $stamps[0];
    }

    private function getKey(Envelope $envelope): string
    {
        return (string) $this->getStamp($envelope)->getKey();
    }
}
