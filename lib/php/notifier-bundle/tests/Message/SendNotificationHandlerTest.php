<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Message;

use Alchemy\NotifierBundle\Delivery\NotificationDeliverer;
use Alchemy\NotifierBundle\Manager\SubscriptionManager;
use Alchemy\NotifierBundle\Message\SendNotification;
use Alchemy\NotifierBundle\Message\SendNotificationHandler;
use Alchemy\NotifierBundle\Model\NotifySelectorDto;
use Alchemy\NotifierBundle\Model\TopicDto;
use PHPUnit\Framework\TestCase;

final class SendNotificationHandlerTest extends TestCase
{
    public function testDeliversToExplicitUsersWithoutResolvingSubscriptions(): void
    {
        $subscriptions = $this->createMock(SubscriptionManager::class);
        $subscriptions->expects(self::never())->method('getSubscriberUserIds');

        $calls = [];
        $handler = new SendNotificationHandler($this->deliverer($calls), $subscriptions);

        $handler(new SendNotification([
            new NotifySelectorDto(
                userIds: ['u1', 'u2'],
                topic: new TopicDto('asset_added', ['x' => 1]),
            ),
        ]));

        self::assertSame([
            ['u1', 'asset_added', ['x' => 1]],
            ['u2', 'asset_added', ['x' => 1]],
        ], $calls);
    }

    public function testMergesEventSubscribersDeduplicatesAndExcludes(): void
    {
        $selector = new NotifySelectorDto(
            event: 'asset_added',
            objectType: 'collection',
            objectId: '42',
            userIds: ['u1', 'u2'],
            topic: new TopicDto('asset_added'),
        );

        $subscriptions = $this->createMock(SubscriptionManager::class);
        $subscriptions->expects(self::once())
            ->method('getSubscriberUserIds')
            ->with($selector)
            ->willReturn(['u2', 'u3']);

        $calls = [];
        $handler = new SendNotificationHandler($this->deliverer($calls), $subscriptions);

        $handler(new SendNotification([$selector], excludeUserId: 'u3'));

        self::assertSame(['u1', 'u2'], array_column($calls, 0));
    }

    public function testUserMatchedBySeveralSelectorsIsNotifiedOnce(): void
    {
        $subscriptions = $this->createMock(SubscriptionManager::class);
        $subscriptions->method('getSubscriberUserIds')->willReturn(['u1', 'u3']);

        $calls = [];
        $handler = new SendNotificationHandler($this->deliverer($calls), $subscriptions);

        $handler(new SendNotification([
            new NotifySelectorDto(
                userIds: ['u1', 'u2'],
                topic: new TopicDto('asset_added'),
            ),
            // u1 also matches this selector, and u3 is a subscriber of the event
            new NotifySelectorDto(
                event: 'asset_added',
                objectType: 'collection',
                objectId: '42',
                userIds: ['u1'],
                topic: new TopicDto('asset_commented'),
            ),
        ]));

        // u1 keeps the topic of the first selector that matched it, and is not notified twice
        self::assertSame([
            ['u1', 'asset_added', []],
            ['u2', 'asset_added', []],
            ['u3', 'asset_commented', []],
        ], $calls);
    }

    public function testSelectorFallsBackToMessageTopic(): void
    {
        $calls = [];
        $handler = new SendNotificationHandler(
            $this->deliverer($calls),
            $this->createMock(SubscriptionManager::class),
        );

        $handler(new SendNotification(
            [new NotifySelectorDto(userIds: ['u1'])],
            topic: new TopicDto('fallback_topic', ['y' => 2]),
        ));

        self::assertSame([['u1', 'fallback_topic', ['y' => 2]]], $calls);
    }

    public function testThrowsWhenNoTopicIsResolvable(): void
    {
        $handler = new SendNotificationHandler(
            $this->createMock(NotificationDeliverer::class),
            $this->createMock(SubscriptionManager::class),
        );

        $this->expectException(\InvalidArgumentException::class);
        $handler(new SendNotification([new NotifySelectorDto(userIds: ['u1'])]));
    }

    /**
     * Records each delivery as [userId, topic, params].
     *
     * @param array<int, array{0: string, 1: string, 2: array}> $calls
     */
    private function deliverer(array &$calls): NotificationDeliverer
    {
        $deliverer = $this->createMock(NotificationDeliverer::class);
        $deliverer->method('deliver')->willReturnCallback(
            function (string $userId, string $topic, array $params = []) use (&$calls): void {
                $calls[] = [$userId, $topic, $params];
            }
        );

        return $deliverer;
    }
}
