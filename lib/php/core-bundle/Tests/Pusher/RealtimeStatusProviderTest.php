<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Tests\Pusher;

use Alchemy\CoreBundle\Pusher\RealtimeStatusProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Pusher\ApiErrorException;
use Pusher\Pusher;
use Pusher\PusherException;

class RealtimeStatusProviderTest extends TestCase
{
    private function createPusher(string $host = 'sockudo'): Pusher&MockObject
    {
        $pusher = $this->createMock(Pusher::class);
        $pusher->method('getSettings')->willReturn(['app_id' => 'phrasea', 'host' => $host]);

        return $pusher;
    }

    /**
     * @param array<string, array<string, int>> $channels
     */
    private static function channelsResult(array $channels): object
    {
        return (object) [
            'channels' => array_map(static fn (array $info): object => (object) $info, $channels),
        ];
    }

    public function testDisabledDoesNotCallTheServer(): void
    {
        $pusher = $this->createPusher();
        $pusher->expects($this->never())->method('getChannels');

        $status = new RealtimeStatusProvider($pusher, disabled: true)->getStatus();

        $this->assertFalse($status->enabled);
        $this->assertFalse($status->reachable);
        $this->assertSame('phrasea', $status->appId);
        $this->assertSame([], $status->users);
        $this->assertSame([], $status->channelsByType);
    }

    public function testNoHostDoesNotCallTheServer(): void
    {
        $pusher = $this->createPusher('');
        $pusher->expects($this->never())->method('getChannels');

        $status = new RealtimeStatusProvider($pusher)->getStatus();

        $this->assertFalse($status->enabled);
        $this->assertFalse($status->reachable);
    }

    public function testChannelsAreGroupedByTypeAndUsersExtracted(): void
    {
        $pusher = $this->createPusher();
        $pusher->expects($this->once())
            ->method('getChannels')
            ->with(['info' => 'subscription_count'])
            ->willReturn(self::channelsResult([
                'private-us.er-1b2c' => ['subscription_count' => 2],
                'private-us.er-a.b' => ['subscription_count' => 1],
                'private-workspace-w1' => ['subscription_count' => 3],
                'private-workspace-w2' => ['subscription_count' => 1],
                'private-thread-asset.42' => ['subscription_count' => 4],
                'presence-room-1' => ['subscription_count' => 5],
                'public' => ['subscription_count' => 6],
            ]));

        // Same normalization as PusherManager: ":" is not allowed in a channel name
        $status = new RealtimeStatusProvider($pusher, 'private-us:er-')->getStatus();

        $this->assertTrue($status->enabled);
        $this->assertTrue($status->reachable);
        $this->assertNull($status->error);
        $this->assertNotNull($status->latencyMs);
        $this->assertSame(['1b2c' => 2, 'a.b' => 1], $status->users);
        $this->assertSame([
            'public' => ['channels' => 1, 'subscriptions' => 6],
            'room' => ['channels' => 1, 'subscriptions' => 5],
            'thread' => ['channels' => 1, 'subscriptions' => 4],
            'us.er' => ['channels' => 2, 'subscriptions' => 3],
            'workspace' => ['channels' => 2, 'subscriptions' => 4],
        ], $status->channelsByType);
        $this->assertSame(7, $status->channelCount);
        $this->assertSame(22, $status->subscriptionCount);
    }

    public function testDefaultUserPrefix(): void
    {
        $pusher = $this->createPusher();
        $pusher->method('getChannels')->willReturn(self::channelsResult([
            'private-user-u1' => ['subscription_count' => 3],
            'private-workspace-w1' => ['subscription_count' => 1],
        ]));

        $status = new RealtimeStatusProvider($pusher)->getStatus();

        $this->assertSame(['u1' => 3], $status->users);
        $this->assertSame([
            'user' => ['channels' => 1, 'subscriptions' => 3],
            'workspace' => ['channels' => 1, 'subscriptions' => 1],
        ], $status->channelsByType);
    }

    public function testUnreachableServer(): void
    {
        $pusher = $this->createPusher();
        $pusher->expects($this->once())
            ->method('getChannels')
            ->willThrowException(new PusherException('Connection refused'));

        $status = new RealtimeStatusProvider($pusher)->getStatus();

        $this->assertTrue($status->enabled);
        $this->assertFalse($status->reachable);
        $this->assertSame('Connection refused', $status->error);
        $this->assertNull($status->latencyMs);
        $this->assertSame(0, $status->channelCount);
        $this->assertSame([], $status->users);
        $this->assertSame([], $status->channelsByType);
    }

    public function testServerErrorIsNotRetried(): void
    {
        $pusher = $this->createPusher();
        $pusher->expects($this->once())
            ->method('getChannels')
            ->willThrowException(new ApiErrorException('Internal error', 500));

        $status = new RealtimeStatusProvider($pusher)->getStatus();

        $this->assertFalse($status->reachable);
        $this->assertSame('(Status 500) Internal error', $status->error);
    }

    public function testSubscriptionCountRefusedFallsBackToPlainList(): void
    {
        $pusher = $this->createPusher();
        $calls = [];
        $pusher->expects($this->exactly(2))
            ->method('getChannels')
            ->willReturnCallback(static function (array $params = []) use (&$calls): object {
                $calls[] = $params;
                if ([] !== $params) {
                    throw new ApiErrorException('Unsupported info', 400);
                }

                return self::channelsResult([
                    'private-user-u1' => [],
                    'private-workspace-w1' => [],
                    'private-workspace-w2' => [],
                ]);
            });

        $status = new RealtimeStatusProvider($pusher)->getStatus();

        $this->assertSame([['info' => 'subscription_count'], []], $calls);
        $this->assertTrue($status->reachable);
        $this->assertSame(3, $status->channelCount);
        $this->assertNull($status->subscriptionCount);
        $this->assertSame(['u1' => null], $status->users);
        $this->assertSame([
            'user' => ['channels' => 1, 'subscriptions' => null],
            'workspace' => ['channels' => 2, 'subscriptions' => null],
        ], $status->channelsByType);
    }
}
