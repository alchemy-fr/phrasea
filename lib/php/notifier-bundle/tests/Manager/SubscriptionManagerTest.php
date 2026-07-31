<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Manager;

use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Manager\SubscriberManager;
use Alchemy\NotifierBundle\Manager\SubscriptionManager;
use Alchemy\NotifierBundle\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class SubscriptionManagerTest extends TestCase
{
    private const string USER_ID = '11111111-1111-1111-1111-111111111111';

    public function testGetSubscribedEventsReturnsRepositoryResult(): void
    {
        $subscriber = new Subscriber(self::USER_ID);

        $subscriberManager = $this->createMock(SubscriberManager::class);
        $subscriberManager->method('find')->with(self::USER_ID)->willReturn($subscriber);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->expects(self::once())
            ->method('findSubscribedEvents')
            ->with($subscriber, 'asset', '42')
            ->willReturn(['asset:update', 'asset:delete']);

        $manager = new SubscriptionManager($this->createMock(EntityManagerInterface::class), $repository, $subscriberManager);

        self::assertSame(['asset:update', 'asset:delete'], $manager->getSubscribedEvents(self::USER_ID, 'asset', '42'));
    }

    public function testGetSubscribedEventsIsEmptyForUnknownSubscriber(): void
    {
        $subscriberManager = $this->createMock(SubscriberManager::class);
        $subscriberManager->method('find')->willReturn(null);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->expects(self::never())->method('findSubscribedEvents');

        $manager = new SubscriptionManager($this->createMock(EntityManagerInterface::class), $repository, $subscriberManager);

        self::assertSame([], $manager->getSubscribedEvents(self::USER_ID, 'asset', '42'));
    }
}
