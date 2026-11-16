<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Manager;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Entity\NotificationPreference;
use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Manager\PreferenceManager;
use Alchemy\NotifierBundle\Repository\NotificationPreferenceRepository;
use Alchemy\NotifierBundle\Topic\TopicRegistry;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PreferenceManagerTest extends TestCase
{
    private const string USER_ID = '11111111-1111-1111-1111-111111111111';

    public function testChannelNotInTopicIsDisabled(): void
    {
        $manager = $this->manager($this->registry(), $this->createStub(EntityManagerInterface::class), $repo = $this->repo());
        $repo->expects(self::never())->method('findOneForChannel');

        self::assertFalse($manager->isChannelEnabled($this->subscriber(), 'asset.comment', ChannelType::Sms));
    }

    public function testChannelEnabledByDefaultWhenNoPreference(): void
    {
        $repo = $this->repo();
        $repo->method('findOneForChannel')->willReturn(null);
        $manager = $this->manager($this->registry(), $this->createStub(EntityManagerInterface::class), $repo);

        self::assertTrue($manager->isChannelEnabled($this->subscriber(), 'asset.comment', ChannelType::Email));
    }

    public function testChannelDisabledByPreference(): void
    {
        $subscriber = $this->subscriber();
        $preference = new NotificationPreference($subscriber, 'asset.comment', ChannelType::Email, false);

        $repo = $this->repo();
        $repo->method('findOneForChannel')->willReturn($preference);
        $manager = $this->manager($this->registry(), $this->createStub(EntityManagerInterface::class), $repo);

        self::assertFalse($manager->isChannelEnabled($subscriber, 'asset.comment', ChannelType::Email));
    }

    public function testPreferenceOfANonConfigurableTopicIsIgnored(): void
    {
        $subscriber = $this->subscriber();
        $repo = $this->repo();
        $repo->method('findOneForChannel')->willReturn(new NotificationPreference($subscriber, 'account.security', ChannelType::Email, false));
        $manager = $this->manager($this->registry(), $this->createStub(EntityManagerInterface::class), $repo);

        self::assertTrue($manager->isChannelEnabled($subscriber, 'account.security', ChannelType::Email));
        self::assertFalse($manager->isChannelEnabled($subscriber, 'account.security', ChannelType::Sms));
    }

    public function testSetPreferencesCreatesRowWhenAbsent(): void
    {
        $subscriber = $this->subscriber();
        $repo = $this->repo();
        $repo->method('findForSubscriber')->willReturn([]);

        $persisted = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted = $entity;
        });
        $em->expects(self::once())->method('flush');

        $this->manager($this->registry(), $em, $repo)->setPreferences($subscriber, [
            ['topic' => 'asset.comment', 'channel' => ChannelType::Email, 'enabled' => false],
        ]);

        self::assertInstanceOf(NotificationPreference::class, $persisted);
        self::assertSame('asset.comment', $persisted->getTopic());
        self::assertSame(ChannelType::Email, $persisted->getChannel());
        self::assertFalse($persisted->isEnabled());
    }

    public function testSetPreferencesUpdatesExistingRow(): void
    {
        $subscriber = $this->subscriber();
        $existing = new NotificationPreference($subscriber, 'asset.comment', ChannelType::Email, true);
        $repo = $this->repo();
        $repo->method('findForSubscriber')->willReturn([$existing]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::once())->method('flush');

        $this->manager($this->registry(), $em, $repo)->setPreferences($subscriber, [
            ['topic' => 'asset.comment', 'channel' => ChannelType::Email, 'enabled' => false],
        ]);

        self::assertFalse($existing->isEnabled());
    }

    public function testPreferencesAreFlushedOnce(): void
    {
        $subscriber = $this->subscriber();
        $repo = $this->repo();
        $repo->expects(self::once())->method('findForSubscriber')->willReturn([]);
        $repo->expects(self::never())->method('findOneForChannel');

        /** @var list<NotificationPreference> $persisted */
        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(2))->method('persist')->willReturnCallback(static function (NotificationPreference $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $em->expects(self::once())->method('flush');

        $this->manager($this->registry(), $em, $repo)->setPreferences($subscriber, [
            ['topic' => 'asset.comment', 'channel' => ChannelType::Email, 'enabled' => false],
            ['topic' => 'asset.comment', 'channel' => ChannelType::InApp, 'enabled' => false],
            ['topic' => 'asset.comment', 'channel' => ChannelType::Email, 'enabled' => true],
        ]);

        self::assertCount(2, $persisted);
        self::assertSame(ChannelType::Email, $persisted[0]->getChannel());
        self::assertTrue($persisted[0]->isEnabled(), 'The last entry for a (topic, channel) pair wins');
        self::assertSame(ChannelType::InApp, $persisted[1]->getChannel());
        self::assertFalse($persisted[1]->isEnabled());
    }

    /**
     * @return iterable<string, array{string, ChannelType}>
     */
    public static function rejectedPreferenceProvider(): iterable
    {
        yield 'unknown topic' => ['nope.nope', ChannelType::Email];
        yield 'non configurable topic' => ['account.security', ChannelType::Email];
        yield 'channel not delivered by the topic' => ['asset.comment', ChannelType::Sms];
        yield 'too long topic' => [str_repeat('a', 300), ChannelType::Email];
    }

    #[DataProvider('rejectedPreferenceProvider')]
    public function testUnknownOrNonConfigurableTopicIsRejected(string $topic, ChannelType $channel): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        $manager = $this->manager($this->registry(), $em, $this->repo());

        $this->expectException(\InvalidArgumentException::class);
        $manager->setPreferences($this->subscriber(), [
            ['topic' => 'asset.comment', 'channel' => ChannelType::Email, 'enabled' => false],
            ['topic' => $topic, 'channel' => $channel, 'enabled' => false],
        ]);
    }

    public function testEffectivePreferencesSkipsNonConfigurableTopicsAndAppliesOverrides(): void
    {
        $subscriber = $this->subscriber();
        $override = new NotificationPreference($subscriber, 'asset.comment', ChannelType::Email, false);

        $repo = $this->repo();
        $repo->method('findForSubscriber')->willReturn([$override]);

        $registry = new TopicRegistry([
            'asset.comment' => ['channels' => ['email', 'in_app'], 'importance' => 'normal', 'user_configurable' => true],
            'account.security' => ['channels' => ['email'], 'importance' => 'high', 'user_configurable' => false],
        ]);

        $manager = $this->manager($registry, $this->createStub(EntityManagerInterface::class), $repo);
        $result = $manager->getEffectivePreferences($subscriber);

        self::assertSame([
            ['topic' => 'asset.comment', 'channel' => 'email', 'enabled' => false],
            ['topic' => 'asset.comment', 'channel' => 'in_app', 'enabled' => true],
        ], $result);
    }

    private function manager(TopicRegistry $registry, EntityManagerInterface $em, NotificationPreferenceRepository $repo): PreferenceManager
    {
        return new PreferenceManager($em, $repo, $registry);
    }

    private function registry(): TopicRegistry
    {
        return new TopicRegistry([
            'asset.comment' => ['channels' => ['email', 'in_app'], 'importance' => 'normal', 'user_configurable' => true],
            'account.security' => ['channels' => ['email'], 'importance' => 'high', 'user_configurable' => false],
        ]);
    }

    private function repo(): NotificationPreferenceRepository
    {
        return $this->createMock(NotificationPreferenceRepository::class);
    }

    private function subscriber(): Subscriber
    {
        return new Subscriber(self::USER_ID);
    }
}
