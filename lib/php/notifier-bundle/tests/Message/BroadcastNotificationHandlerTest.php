<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Message;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Delivery\NotificationDeliverer;
use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Manager\SubscriberManager;
use Alchemy\NotifierBundle\Message\BroadcastNotification;
use Alchemy\NotifierBundle\Message\BroadcastNotificationHandler;
use Alchemy\NotifierBundle\Subscriber\DirectoryUser;
use Alchemy\NotifierBundle\Subscriber\SubscriberInfo;
use Alchemy\NotifierBundle\Subscriber\UserDirectoryInterface;
use Alchemy\NotifierBundle\Subscriber\UserDirectoryRegistry;
use PHPUnit\Framework\TestCase;

final class BroadcastNotificationHandlerTest extends TestCase
{
    public function testDeliversTheTopicToEveryUserOfTheDirectory(): void
    {
        $delivered = [];
        $handler = $this->handler([
            new DirectoryUser('u1'),
            new DirectoryUser('u2'),
            new DirectoryUser('u3'),
        ], $delivered);

        $handler(new BroadcastNotification('asset_added', ['x' => 1]));

        self::assertSame([
            ['u1', 'asset_added', ['x' => 1], null],
            ['u2', 'asset_added', ['x' => 1], null],
            ['u3', 'asset_added', ['x' => 1], null],
        ], $delivered);
    }

    public function testExcludedUserIsSkipped(): void
    {
        $delivered = [];
        $handler = $this->handler([new DirectoryUser('u1'), new DirectoryUser('u2')], $delivered);

        $handler(new BroadcastNotification('asset_added', [], excludeUserId: 'u1'));

        self::assertSame([['u2', 'asset_added', [], null]], $delivered);
    }

    public function testChannelsAreForwardedAsEnums(): void
    {
        $delivered = [];
        $handler = $this->handler([new DirectoryUser('u1')], $delivered);

        $handler(new BroadcastNotification('asset_added', [], channels: ['email']));

        self::assertSame([[ChannelType::Email]], array_column($delivered, 3));
    }

    public function testAFailingRecipientDoesNotAbortTheBroadcast(): void
    {
        $delivered = [];
        $handler = $this->handler([
            new DirectoryUser('boom'),
            new DirectoryUser('u2'),
        ], $delivered);

        $handler(new BroadcastNotification('asset_added'));

        self::assertSame([['u2', 'asset_added', [], null]], $delivered);
    }

    public function testDirectoryInfoIsPassedToTheSubscriberManager(): void
    {
        $info = new SubscriberInfo(email: 'a@b.c');
        $seen = [];

        $subscriberManager = $this->createMock(SubscriberManager::class);
        $subscriberManager->method('getOrCreate')->willReturnCallback(
            function (string $userId, ?SubscriberInfo $info = null) use (&$seen): Subscriber {
                $seen[] = [$userId, $info];

                return new Subscriber($userId);
            }
        );

        $handler = new BroadcastNotificationHandler(
            $this->registry([new DirectoryUser('u1', $info)]),
            $subscriberManager,
            $this->createMock(NotificationDeliverer::class),
        );

        $handler(new BroadcastNotification('asset_added'));

        self::assertSame([['u1', $info]], $seen);
    }

    /**
     * @param array<int, DirectoryUser>                                    $users
     * @param array<int, array{0: string, 1: string, 2: array, 3: ?array}> $delivered
     */
    private function handler(array $users, array &$delivered): BroadcastNotificationHandler
    {
        $subscriberManager = $this->createMock(SubscriberManager::class);
        $subscriberManager->method('getOrCreate')->willReturnCallback(
            static function (string $userId): Subscriber {
                if ('boom' === $userId) {
                    throw new \RuntimeException('unreachable user');
                }

                return new Subscriber($userId);
            }
        );

        $deliverer = $this->createMock(NotificationDeliverer::class);
        $deliverer->method('deliverTo')->willReturnCallback(
            function (Subscriber $subscriber, string $topic, array $params = [], array $options = [], ?array $channels = null) use (&$delivered): void {
                $delivered[] = [$subscriber->getUserId(), $topic, $params, $channels];
            }
        );

        return new BroadcastNotificationHandler($this->registry($users), $subscriberManager, $deliverer);
    }

    /**
     * @param array<int, DirectoryUser> $users
     */
    private function registry(array $users): UserDirectoryRegistry
    {
        $directory = new class($users) implements UserDirectoryInterface {
            /**
             * @param array<int, DirectoryUser> $users
             */
            public function __construct(private readonly array $users)
            {
            }

            public function getName(): string
            {
                return 'test';
            }

            public function getLabel(): string
            {
                return 'Test';
            }

            public function iterate(): iterable
            {
                yield from $this->users;
            }
        };

        return new UserDirectoryRegistry([$directory], 'test');
    }
}
