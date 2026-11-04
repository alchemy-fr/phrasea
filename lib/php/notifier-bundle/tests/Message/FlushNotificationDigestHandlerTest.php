<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Message;

use Alchemy\NotifierBundle\Digest\DigestFlusher;
use Alchemy\NotifierBundle\Message\FlushNotificationDigest;
use Alchemy\NotifierBundle\Message\FlushNotificationDigestHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class FlushNotificationDigestHandlerTest extends TestCase
{
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-08-31 10:00:00');
    }

    public function testADueBucketIsFlushedWithoutRescheduling(): void
    {
        $flusher = $this->createMock(DigestFlusher::class);
        $flusher->expects(self::once())->method('flushIfDue')->with('digest-1')->willReturn(true);

        $dispatched = [];
        $handler = new FlushNotificationDigestHandler($flusher, $this->bus($dispatched), $this->clock);

        $handler(new FlushNotificationDigest('digest-1', $this->clock->now()->getTimestamp()));

        self::assertSame([], $dispatched);
    }

    public function testANotDueBucketReschedulesTheProbeAtTheRemainingDelay(): void
    {
        $flusher = $this->createMock(DigestFlusher::class);
        $flusher->method('flushIfDue')->willReturn(false);
        $flusher->method('getRemainingDelay')->with('digest-1')->willReturn(120);

        $dispatched = [];
        $handler = new FlushNotificationDigestHandler($flusher, $this->bus($dispatched), $this->clock);

        $handler(new FlushNotificationDigest('digest-1', $this->clock->now()->getTimestamp()));

        self::assertCount(1, $dispatched);
        [$message, $stamps] = $dispatched[0];
        self::assertInstanceOf(FlushNotificationDigest::class, $message);
        self::assertSame('digest-1', $message->digestId);
        self::assertSame($this->clock->now()->getTimestamp() + 120, $message->notBefore);
        self::assertEquals([new DelayStamp(120_000)], $stamps);
    }

    public function testAVanishedBucketStopsTheProbe(): void
    {
        $flusher = $this->createMock(DigestFlusher::class);
        $flusher->method('flushIfDue')->willReturn(false);
        $flusher->method('getRemainingDelay')->willReturn(null);

        $dispatched = [];
        $handler = new FlushNotificationDigestHandler($flusher, $this->bus($dispatched), $this->clock);

        $handler(new FlushNotificationDigest('digest-1', $this->clock->now()->getTimestamp()));

        self::assertSame([], $dispatched);
    }

    public function testAnUnhonoredDelayGivesUpInsteadOfBusyLooping(): void
    {
        // Sync transport: the handler runs immediately although the probe was
        // delayed by 10 minutes. It must neither flush nor reschedule.
        $flusher = $this->createMock(DigestFlusher::class);
        $flusher->expects(self::never())->method('flushIfDue');

        $dispatched = [];
        $handler = new FlushNotificationDigestHandler($flusher, $this->bus($dispatched), $this->clock);

        $handler(new FlushNotificationDigest('digest-1', $this->clock->now()->getTimestamp() + 600));

        self::assertSame([], $dispatched);
    }

    /**
     * @param array<int, array{0: object, 1: array<int, object>}> $dispatched
     */
    private function bus(array &$dispatched): MessageBusInterface
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(
            static function (object $message, array $stamps = []) use (&$dispatched): Envelope {
                $dispatched[] = [$message, $stamps];

                return new Envelope($message);
            }
        );

        return $bus;
    }
}
