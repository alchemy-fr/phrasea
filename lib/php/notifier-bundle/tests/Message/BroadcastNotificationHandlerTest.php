<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Message;

use Alchemy\NotifierBundle\Delivery\NotificationDeliverer;
use Alchemy\NotifierBundle\Message\BroadcastNotification;
use Alchemy\NotifierBundle\Message\BroadcastNotificationHandler;
use Alchemy\NotifierBundle\Repository\SubscriberRepository;
use PHPUnit\Framework\TestCase;

final class BroadcastNotificationHandlerTest extends TestCase
{
    public function testDeliversTheTopicToEverySubscriber(): void
    {
        $repository = $this->createMock(SubscriberRepository::class);
        $repository->method('iterateUserIds')->willReturn(['u1', 'u2', 'u3']);

        $delivered = [];
        $deliverer = $this->createMock(NotificationDeliverer::class);
        $deliverer->method('deliver')->willReturnCallback(
            function (string $userId, string $topic, array $params = []) use (&$delivered): void {
                $delivered[] = [$userId, $topic, $params];
            }
        );

        (new BroadcastNotificationHandler($repository, $deliverer))(
            new BroadcastNotification('asset_added', ['x' => 1])
        );

        self::assertSame([
            ['u1', 'asset_added', ['x' => 1]],
            ['u2', 'asset_added', ['x' => 1]],
            ['u3', 'asset_added', ['x' => 1]],
        ], $delivered);
    }
}
