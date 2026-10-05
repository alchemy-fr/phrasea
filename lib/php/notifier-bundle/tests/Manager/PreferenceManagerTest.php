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
use PHPUnit\Framework\TestCase;

final class PreferenceManagerTest extends TestCase
{
    private const string USER_ID = '11111111-1111-1111-1111-111111111111';

    public function testChannelNotInTopicIsDisabled(): void
    {
        $manager = $this->manager($this->registry(), $this->createMock(EntityManagerInterface::class), $repo = $this->repo());
        $repo->expects(self::never())->method('findOneForChannel');

        self::assertFalse($manager->isChannelEnabled($this->subscriber(), 'asset.comment', ChannelType::Sms));
    }

    public function testChannelEnabledByDefaultWhenNoPreference(): void
    {
        $repo = $this->repo();
        $repo->method('findOneForChannel')->willReturn(null);
        $manager = $this->manager($this->registry(), $this->createMock(EntityManagerInterface::class), $repo);

        self::assertTrue($manager->isChannelEnabled($this->subscriber(), 'asset.comment', ChannelType::Email));
    }

    public function testChannelDisabledByPreference(): void
    {
        $subscriber = $this->subscriber();
        $preference = new NotificationPreference($subscriber, 'asset.comment', ChannelType::Email, false);

        $repo = $this->repo();
        $repo->method('findOneForChannel')->willReturn($preference);
        $manager = $this->manager($this->registry(), $this->createMock(EntityManagerInterface::class), $repo);

        self::assertFalse($manager->isChannelEnabled($subscriber, 'asset.comment', ChannelType::Email));
    }

    public function testSetPreferenceCreatesRowWhenAbsent(): void
    {
        $subscriber = $this->subscriber();
        $repo = $this->repo();
        $repo->method('findOneForChannel')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(NotificationPreference::class));
        $em->expects(self::once())->method('flush');

        $manager = $this->manager($this->registry(), $em, $repo);
        $preference = $manager->setPreference($subscriber, 'asset.comment', ChannelType::Email, false);

        self::assertFalse($preference->isEnabled());
    }

    public function testSetPreferenceUpdatesExistingRow(): void
    {
        $subscriber = $this->subscriber();
        $existing = new NotificationPreference($subscriber, 'asset.comment', ChannelType::Email, true);
        $repo = $this->repo();
        $repo->method('findOneForChannel')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::once())->method('flush');

        $manager = $this->manager($this->registry(), $em, $repo);
        $preference = $manager->setPreference($subscriber, 'asset.comment', ChannelType::Email, false);

        self::assertSame($existing, $preference);
        self::assertFalse($preference->isEnabled());
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

        $manager = $this->manager($registry, $this->createMock(EntityManagerInterface::class), $repo);
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
