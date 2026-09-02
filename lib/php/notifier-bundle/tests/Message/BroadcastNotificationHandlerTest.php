<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Message;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Delivery\NotificationDeliverer;
use Alchemy\NotifierBundle\Entity\Broadcast;
use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Manager\SubscriberManager;
use Alchemy\NotifierBundle\Message\BroadcastNotification;
use Alchemy\NotifierBundle\Message\BroadcastNotificationHandler;
use Alchemy\NotifierBundle\Repository\BroadcastRepository;
use Alchemy\NotifierBundle\Subscriber\DirectoryUser;
use Alchemy\NotifierBundle\Subscriber\SubscriberInfo;
use Alchemy\NotifierBundle\Subscriber\UserDirectoryInterface;
use Alchemy\NotifierBundle\Subscriber\UserDirectoryRegistry;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class BroadcastNotificationHandlerTest extends TestCase
{
    public function testDeliversTheTopicToEveryUserOfTheDirectory(): void
    {
        $delivered = [];
        $broadcast = $this->broadcast(payload: ['x' => 1]);
        $handler = $this->handler([
            new DirectoryUser('u1'),
            new DirectoryUser('u2'),
            new DirectoryUser('u3'),
        ], $delivered, $broadcast);

        $handler(new BroadcastNotification($broadcast->getId()));

        self::assertSame([
            ['u1', 'asset_added', ['x' => 1], null],
            ['u2', 'asset_added', ['x' => 1], null],
            ['u3', 'asset_added', ['x' => 1], null],
        ], $delivered);
    }

    public function testExcludedUserIsSkipped(): void
    {
        $delivered = [];
        $broadcast = $this->broadcast(excludeUserId: 'u1');
        $handler = $this->handler([new DirectoryUser('u1'), new DirectoryUser('u2')], $delivered, $broadcast);

        $handler(new BroadcastNotification($broadcast->getId()));

        self::assertSame([['u2', 'asset_added', [], null]], $delivered);
    }

    public function testChannelsAreForwardedAsEnums(): void
    {
        $delivered = [];
        $broadcast = $this->broadcast(channels: ['email']);
        $handler = $this->handler([new DirectoryUser('u1')], $delivered, $broadcast);

        $handler(new BroadcastNotification($broadcast->getId()));

        self::assertSame([[ChannelType::Email]], array_column($delivered, 3));
    }

    public function testAFailingRecipientDoesNotAbortTheBroadcast(): void
    {
        $delivered = [];
        $broadcast = $this->broadcast();
        $handler = $this->handler([
            new DirectoryUser('boom'),
            new DirectoryUser('u2'),
        ], $delivered, $broadcast);

        $handler(new BroadcastNotification($broadcast->getId()));

        self::assertSame([['u2', 'asset_added', [], null]], $delivered);
    }

    public function testTheHistoryRowIsCompletedWithTheCounts(): void
    {
        $delivered = [];
        $broadcast = $this->broadcast();
        $handler = $this->handler([
            new DirectoryUser('u1'),
            new DirectoryUser('boom'),
            new DirectoryUser('u2'),
        ], $delivered, $broadcast);

        $handler(new BroadcastNotification($broadcast->getId()));

        self::assertSame(2, $broadcast->getDeliveredCount());
        self::assertSame(1, $broadcast->getFailedCount());
        self::assertNotNull($broadcast->getStartedAt());
        self::assertNotNull($broadcast->getCompletedAt());
    }

    public function testAMissingBroadcastDeliversNothing(): void
    {
        $delivered = [];
        $handler = $this->handler([new DirectoryUser('u1')], $delivered);

        $handler(new BroadcastNotification('gone'));

        self::assertSame([], $delivered);
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

        $broadcast = $this->broadcast();
        $handler = new BroadcastNotificationHandler(
            $this->registry([new DirectoryUser('u1', $info)]),
            $subscriberManager,
            $this->createMock(NotificationDeliverer::class),
            $this->broadcastRepository($broadcast),
            $this->createMock(EntityManagerInterface::class),
        );

        $handler(new BroadcastNotification($broadcast->getId()));

        self::assertSame([['u1', $info]], $seen);
    }

    /**
     * @param array<string, mixed>    $payload
     * @param array<int, string>|null $channels
     */
    private function broadcast(array $payload = [], ?array $channels = null, ?string $excludeUserId = null): Broadcast
    {
        $broadcast = new Broadcast('asset_added', 'test');
        $broadcast->setPayload($payload);
        $broadcast->setChannels($channels);
        $broadcast->setExcludeUserId($excludeUserId);

        return $broadcast;
    }

    /**
     * @param array<int, DirectoryUser>                                    $users
     * @param array<int, array{0: string, 1: string, 2: array, 3: ?array}> $delivered
     */
    private function handler(array $users, array &$delivered, ?Broadcast $broadcast = null): BroadcastNotificationHandler
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

        return new BroadcastNotificationHandler(
            $this->registry($users),
            $subscriberManager,
            $deliverer,
            $this->broadcastRepository($broadcast),
            $this->createMock(EntityManagerInterface::class),
        );
    }

    private function broadcastRepository(?Broadcast $broadcast = null): BroadcastRepository
    {
        $repository = $this->createMock(BroadcastRepository::class);
        $repository->method('find')->willReturn($broadcast);

        return $repository;
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
