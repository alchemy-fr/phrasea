<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Manager;

use Alchemy\NotifierBundle\Entity\Subscription;
use Alchemy\NotifierBundle\Model\NotifySelectorDto;
use Alchemy\NotifierBundle\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;

class SubscriptionManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SubscriptionRepository $repository,
        private readonly SubscriberManager $subscriberManager,
    ) {
    }

    public function subscribe(string $userId, NotifySelectorDto $selector): Subscription
    {
        $subscriber = $this->subscriberManager->getOrCreate($userId);

        $subscription = $this->repository->findOneForObject($subscriber, $selector);
        if (null !== $subscription) {
            return $subscription;
        }

        $subscription = new Subscription($subscriber, $selector->event, $selector->objectType, $selector->objectId);
        $this->em->persist($subscription);
        $this->em->flush();

        return $subscription;
    }

    public function unsubscribe(string $userId, NotifySelectorDto $selector): void
    {
        $subscriber = $this->subscriberManager->find($userId);
        if (null === $subscriber) {
            return;
        }

        $subscription = $this->repository->findOneForObject($subscriber, $selector);
        if (null === $subscription) {
            return;
        }

        $this->em->remove($subscription);
        $this->em->flush();
    }

    public function isSubscribed(string $userId, NotifySelectorDto $selector): bool
    {
        $subscriber = $this->subscriberManager->find($userId);
        if (null === $subscriber) {
            return false;
        }

        return null !== $this->repository->findOneForObject($subscriber, $selector);
    }

    /**
     * @return array<int, string>
     */
    public function getSubscriberUserIds(NotifySelectorDto $selector): array
    {
        return $this->repository->findSubscriberUserIds($selector);
    }
}
