<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Manager;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Entity\Broadcast;
use Alchemy\NotifierBundle\Manager\NotifierManager;
use Alchemy\NotifierBundle\Message\BroadcastNotification;
use Alchemy\NotifierBundle\Message\SendEmailNotification;
use Alchemy\NotifierBundle\Message\SendNotification;
use Alchemy\NotifierBundle\Model\BroadcastOptions;
use Alchemy\NotifierBundle\Model\NotifyOptions;
use Alchemy\NotifierBundle\Model\NotifySelectorDto;
use Alchemy\NotifierBundle\Model\TopicDto;
use Alchemy\NotifierBundle\NotifierState;
use Alchemy\NotifierBundle\Subscriber\KeycloakUserDirectory;
use Alchemy\NotifierBundle\Subscriber\SubscriberUserDirectory;
use Alchemy\NotifierBundle\Subscriber\UserDirectoryInterface;
use Alchemy\NotifierBundle\Subscriber\UserDirectoryRegistry;
use Alchemy\NotifierBundle\Topic\TopicRegistry;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class NotifierManagerTest extends TestCase
{
    public function testDisabledManagerDoesNotDispatch(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $manager = $this->manager($bus, false);
        $manager->notifyUser('u1', 'asset_added', ['a' => 1]);
        $manager->notify([new NotifySelectorDto(event: 'asset_added', topic: new TopicDto('asset_added'))]);

        self::assertFalse($manager->isEnabled());
    }

    public function testEmptySelectorsDoesNotDispatch(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $this->manager($bus)->notify([]);
    }

    public function testNotifyUserBuildsAUserSelector(): void
    {
        $captured = null;
        $manager = $this->manager($this->capturingBus($captured));

        $manager->notifyUser('u1', 'asset_added', ['x' => 1]);

        self::assertInstanceOf(SendNotification::class, $captured);
        self::assertCount(1, $captured->selectors);

        $selector = $captured->selectors[0];
        self::assertSame(['u1'], $selector->userIds);
        self::assertNull($selector->event);
        self::assertSame('asset_added', $selector->topic->topic);
        self::assertSame(['x' => 1], $selector->topic->params);
        self::assertNull($captured->excludeUserId);
    }

    public function testNotifyUsersCarriesEveryRecipient(): void
    {
        $captured = null;
        $manager = $this->manager($this->capturingBus($captured));

        $manager->notifyUsers(['u1', 'u2'], 'asset_added');

        self::assertInstanceOf(SendNotification::class, $captured);
        self::assertSame(['u1', 'u2'], $captured->selectors[0]->userIds);
    }

    public function testNotifyDispatchesSelectorsAndExclusion(): void
    {
        $captured = null;
        $manager = $this->manager($this->capturingBus($captured));

        $selector = new NotifySelectorDto(
            event: 'asset_added',
            objectType: 'collection',
            objectId: '42',
            topic: new TopicDto('asset_added', ['x' => 1]),
        );
        $manager->notify([$selector], new NotifyOptions(excludeUserId: 'author'));

        self::assertInstanceOf(SendNotification::class, $captured);
        self::assertSame([$selector], $captured->selectors);
        self::assertSame('author', $captured->excludeUserId);
    }

    public function testNotifyEmailDispatchesTransactionalEmail(): void
    {
        $captured = null;
        $manager = $this->manager($this->capturingBus($captured));

        $manager->notifyEmail('bob@example.com', 'asset_added', ['downloadUrl' => 'https://x'], 'fr');

        self::assertInstanceOf(SendEmailNotification::class, $captured);
        self::assertSame('bob@example.com', $captured->email);
        self::assertSame('asset_added', $captured->topic);
        self::assertSame(['downloadUrl' => 'https://x'], $captured->params);
        self::assertSame('fr', $captured->locale);
    }

    public function testNotifyEmailUnknownTopicThrowsBeforeDispatch(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $this->expectException(\InvalidArgumentException::class);
        $this->manager($bus)->notifyEmail('bob@example.com', 'not_declared');
    }

    public function testNotifyEmailDisabledManagerDoesNothing(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $this->manager($bus, false)->notifyEmail('bob@example.com', 'asset_added');
    }

    public function testBroadcastDispatchesToAllSubscribers(): void
    {
        $captured = null;
        $manager = $this->manager($this->capturingBus($captured));

        $manager->broadcast('asset_added', ['x' => 1]);

        self::assertInstanceOf(BroadcastNotification::class, $captured);
        self::assertNotSame('', $captured->broadcastId);
    }

    public function testBroadcastRecordsItsHistoryRow(): void
    {
        $persisted = [];
        $captured = null;
        $manager = $this->manager($this->capturingBus($captured), persisted: $persisted);

        $manager->broadcast('asset_added', ['x' => 1], new BroadcastOptions(
            channels: [ChannelType::Email],
            excludeUserId: 'u9',
            initiatorUserId: 'admin-1',
        ));

        $broadcast = $persisted[0];
        self::assertInstanceOf(Broadcast::class, $broadcast);
        self::assertSame('asset_added', $broadcast->getTopic());
        self::assertSame(['x' => 1], $broadcast->getPayload());
        self::assertSame(['email'], $broadcast->getChannels());
        self::assertSame(KeycloakUserDirectory::NAME, $broadcast->getDirectory());
        self::assertSame('admin-1', $broadcast->getInitiatorUserId());
        self::assertSame('u9', $broadcast->getExcludeUserId());
    }

    public function testTheInitiatorDefaultsToTheAuthenticatedUser(): void
    {
        $persisted = [];
        $captured = null;
        $manager = $this->manager($this->capturingBus($captured), currentUserId: 'current-admin', persisted: $persisted);

        $manager->broadcast('asset_added');

        self::assertSame('current-admin', $persisted[0]->getInitiatorUserId());
    }

    public function testDispatchBroadcastReportsWhenNotificationsAreDisabled(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        self::assertFalse(
            $this->manager($bus, false)->dispatchBroadcast(new Broadcast('asset_added', KeycloakUserDirectory::NAME))
        );
    }

    public function testDispatchBroadcastRejectsAnUnknownAudience(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $this->expectException(\InvalidArgumentException::class);
        $this->manager($bus)->dispatchBroadcast(new Broadcast('asset_added', 'nope'));
    }

    public function testBroadcastCarriesItsOptions(): void
    {
        $persisted = [];
        $captured = null;
        $manager = $this->manager($this->capturingBus($captured), persisted: $persisted);

        $manager->broadcast('asset_added', [], new BroadcastOptions(
            channels: [ChannelType::Email, 'in_app'],
            excludeUserId: 'u1',
            directory: SubscriberUserDirectory::NAME,
        ));

        self::assertCount(1, $persisted);

        $broadcast = $persisted[0];
        self::assertSame(['email', 'in_app'], $broadcast->getChannels());
        self::assertSame('u1', $broadcast->getExcludeUserId());
        self::assertSame(SubscriberUserDirectory::NAME, $broadcast->getDirectory());

        // The worker only gets the id: everything else is read back from the row
        self::assertSame($broadcast->getId(), $captured->broadcastId);
    }

    public function testBroadcastUnknownTopicThrowsBeforeDispatch(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $this->expectException(\InvalidArgumentException::class);
        $this->manager($bus)->broadcast('not_declared');
    }

    public function testBroadcastDisabledManagerDoesNothing(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $this->manager($bus, false)->broadcast('asset_added');
    }

    public function testUnknownTopicThrowsBeforeDispatch(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $this->expectException(\InvalidArgumentException::class);

        $this->manager($bus)->notify([
            new NotifySelectorDto(event: 'whatever', topic: new TopicDto('not_declared')),
        ]);
    }

    public function testNotifyOptionsAreOptional(): void
    {
        $captured = null;
        $manager = $this->manager($this->capturingBus($captured));

        $manager->notify([new NotifySelectorDto(event: 'asset_added', topic: new TopicDto('asset_added'))]);

        self::assertInstanceOf(SendNotification::class, $captured);
        self::assertNull($captured->excludeUserId);
    }

    private function capturingBus(?object &$captured): MessageBusInterface
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturnCallback(
            function (object $message) use (&$captured): Envelope {
                $captured = $message;

                return new Envelope($message);
            }
        );

        return $bus;
    }

    /**
     * @param array<int, object>|null $persisted collects the entities the manager persists
     */
    private function manager(MessageBusInterface $bus, bool $enabled = true, ?string $currentUserId = null, ?array &$persisted = null): NotifierManager
    {
        $security = $this->createMock(Security::class);
        if (null !== $currentUserId) {
            $user = $this->createMock(UserInterface::class);
            $user->method('getUserIdentifier')->willReturn($currentUserId);
            $security->method('getUser')->willReturn($user);
        }

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        return new NotifierManager(
            $bus,
            new TopicRegistry([
                'asset_added' => ['channels' => ['email', 'in_app'], 'importance' => 'normal', 'user_configurable' => true],
            ]),
            new NotifierState($enabled),
            $em,
            new UserDirectoryRegistry([
                $this->directory(KeycloakUserDirectory::NAME),
                $this->directory(SubscriberUserDirectory::NAME),
            ], KeycloakUserDirectory::NAME),
            $security,
        );
    }

    private function directory(string $name): UserDirectoryInterface
    {
        return new class($name) implements UserDirectoryInterface {
            public function __construct(private readonly string $name)
            {
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getLabel(): string
            {
                return $this->name;
            }

            public function iterate(): iterable
            {
                return [];
            }
        };
    }
}
