<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Manager;

use Alchemy\NotifierBundle\Delivery\NotificationDeliverer;
use Alchemy\NotifierBundle\Manager\NotifierManager;
use Alchemy\NotifierBundle\Message\SendNotification;
use Alchemy\NotifierBundle\Model\NotifyOptions;
use Alchemy\NotifierBundle\Model\NotifySelectorDto;
use Alchemy\NotifierBundle\Model\TopicDto;
use Alchemy\NotifierBundle\NotifierState;
use Alchemy\NotifierBundle\Topic\TopicRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

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

    private function manager(MessageBusInterface $bus, bool $enabled = true): NotifierManager
    {
        return new NotifierManager(
            $bus,
            $this->createMock(NotificationDeliverer::class),
            new TopicRegistry([
                'asset_added' => ['channels' => ['email', 'in_app'], 'importance' => 'normal', 'user_configurable' => true],
            ]),
            new NotifierState($enabled),
        );
    }
}
