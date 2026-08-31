<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Digest;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Digest\DigestBuffer;
use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Message\FlushNotificationDigest;
use Alchemy\NotifierBundle\Repository\NotificationDigestRepository;
use Alchemy\NotifierBundle\Topic\DigestConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class DigestBufferTest extends TestCase
{
    public function testTheFirstEventSchedulesTheFlushProbe(): void
    {
        $clock = new MockClock('2026-08-31 10:00:00');
        $subscriber = new Subscriber('user-1');
        $config = new DigestConfig(inactivityDelay: 600, maxDelay: 3600);

        $repository = $this->createMock(NotificationDigestRepository::class);
        $repository->expects(self::once())->method('append')
            ->with(
                $subscriber->getId(),
                'discussion:new_comment',
                'email',
                ['params' => ['object' => 'Asset A'], 'at' => $clock->now()->format(\DateTimeInterface::ATOM)],
                $clock->now(),
            )
            ->willReturn(['id' => 'digest-1', 'inserted' => true]);

        $dispatched = [];
        $buffer = new DigestBuffer($repository, $this->bus($dispatched), $clock);

        $buffer->add($subscriber, 'discussion:new_comment', ChannelType::Email, ['object' => 'Asset A'], $config);

        self::assertCount(1, $dispatched);
        [$message, $stamps] = $dispatched[0];
        self::assertInstanceOf(FlushNotificationDigest::class, $message);
        self::assertSame('digest-1', $message->digestId);
        self::assertSame($clock->now()->getTimestamp() + 600, $message->notBefore);
        self::assertEquals([new DelayStamp(600_000)], $stamps);
    }

    public function testAFollowingEventDoesNotScheduleASecondProbe(): void
    {
        $repository = $this->createMock(NotificationDigestRepository::class);
        $repository->method('append')->willReturn(['id' => 'digest-1', 'inserted' => false]);

        $dispatched = [];
        $buffer = new DigestBuffer($repository, $this->bus($dispatched), new MockClock());

        $buffer->add(new Subscriber('user-1'), 'discussion:new_comment', ChannelType::Email, [], new DigestConfig());

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
