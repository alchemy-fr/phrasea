<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Message;

use Alchemy\NotifierBundle\Delivery\NotificationDeliverer;
use Alchemy\NotifierBundle\Manager\SubscriptionManager;
use Alchemy\NotifierBundle\Message\SendNotification;
use Alchemy\NotifierBundle\Message\SendNotificationHandler;
use PHPUnit\Framework\TestCase;

final class SendNotificationHandlerTest extends TestCase
{
    public function testDeliversToExplicitUsers(): void
    {
        $subscriptions = $this->createMock(SubscriptionManager::class);
        $subscriptions->expects(self::never())->method('getSubscriberUserIds');

        $delivered = [];
        $deliverer = $this->deliverer($delivered);

        $handler = new SendNotificationHandler($deliverer, $subscriptions);
        $handler(new SendNotification('asset.comment', userIds: ['u1', 'u2'], params: ['x' => 1]));

        self::assertSame(['u1', 'u2'], $delivered);
    }

    public function testMergesObjectFollowersDeduplicatesAndExcludes(): void
    {
        $subscriptions = $this->createMock(SubscriptionManager::class);
        $subscriptions->expects(self::once())
            ->method('getSubscriberUserIds')
            ->with('asset', '42')
            ->willReturn(['u2', 'u3']);

        $delivered = [];
        $deliverer = $this->deliverer($delivered);

        $handler = new SendNotificationHandler($deliverer, $subscriptions);
        $handler(new SendNotification(
            'asset.comment',
            userIds: ['u1', 'u2'],
            objectType: 'asset',
            objectId: '42',
            excludeUserId: 'u3',
        ));

        self::assertSame(['u1', 'u2'], $delivered);
    }

    /**
     * @param array<int, string> $delivered
     */
    private function deliverer(array &$delivered): NotificationDeliverer
    {
        $deliverer = $this->createMock(NotificationDeliverer::class);
        $deliverer->method('deliver')->willReturnCallback(
            function (string $userId) use (&$delivered): void {
                $delivered[] = $userId;
            }
        );

        return $deliverer;
    }
}
