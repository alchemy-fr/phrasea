<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Manager;

use Alchemy\NotifierBundle\Delivery\NotificationDeliverer;
use Alchemy\NotifierBundle\Manager\NotifierManager;
use Alchemy\NotifierBundle\Message\SendNotification;
use Alchemy\NotifierBundle\Topic\TopicRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class NotifierManagerTest extends TestCase
{
    public function testDisabledManagerDoesNothing(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');
        $deliverer = $this->createMock(NotificationDeliverer::class);
        $deliverer->expects(self::never())->method('deliver');

        $manager = new NotifierManager($bus, $deliverer, $this->registry(), false);
        $manager->notifyUser('u1', 'asset.comment', ['a' => 1]);

        self::assertFalse($manager->isEnabled());
    }

    public function testEmptyRecipientsDoesNotDispatch(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $manager = new NotifierManager($bus, $this->createMock(NotificationDeliverer::class), $this->registry());
        $manager->notifyUsers([], 'asset.comment');
    }

    public function testNotifyUsersDispatchesDeduplicatedMessage(): void
    {
        $captured = null;
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturnCallback(
            function (object $message) use (&$captured): Envelope {
                $captured = $message;

                return new Envelope($message);
            }
        );

        $manager = new NotifierManager($bus, $this->createMock(NotificationDeliverer::class), $this->registry());
        $manager->notifyUsers(['u1', 'u2', 'u1'], 'asset.comment', ['x' => 1]);

        self::assertInstanceOf(SendNotification::class, $captured);
        self::assertSame('asset.comment', $captured->topic);
        self::assertSame(['u1', 'u2'], $captured->userIds);
        self::assertSame(['x' => 1], $captured->params);
    }

    public function testSyncOptionDeliversInline(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $deliverer = $this->createMock(NotificationDeliverer::class);
        $calls = [];
        $deliverer->expects(self::exactly(2))->method('deliver')->willReturnCallback(
            function (string $userId) use (&$calls): void {
                $calls[] = $userId;
            }
        );

        $manager = new NotifierManager($bus, $deliverer, $this->registry());
        $manager->notifyUsers(['u1', 'u2'], 'asset.comment', [], ['sync' => true]);

        self::assertSame(['u1', 'u2'], $calls);
    }

    public function testNotifyObjectDispatchesWithTargetAndExclusion(): void
    {
        $captured = null;
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturnCallback(
            function (object $message) use (&$captured): Envelope {
                $captured = $message;

                return new Envelope($message);
            }
        );

        $manager = new NotifierManager($bus, $this->createMock(NotificationDeliverer::class), $this->registry());
        $manager->notifyObject('asset', '42', 'asset.comment', ['x' => 1], ['exclude_user_id' => 'author']);

        self::assertInstanceOf(SendNotification::class, $captured);
        self::assertSame('asset', $captured->objectType);
        self::assertSame('42', $captured->objectId);
        self::assertSame('author', $captured->excludeUserId);
    }

    private function registry(): TopicRegistry
    {
        return new TopicRegistry([
            'asset.comment' => ['channels' => ['email', 'in_app'], 'importance' => 'normal', 'user_configurable' => true],
        ]);
    }
}
