<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Manager;

use Alchemy\NotifierBundle\Entity\Subscription;
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

    public function subscribe(string $userId, string $topic, ?string $objectType = null, ?string $objectId = null): Subscription
    {
        $subscriber = $this->subscriberManager->getOrCreate($userId);

        $subscription = $this->repository->findOneForObject($subscriber, $topic, $objectType, $objectId);
        if (null !== $subscription) {
            return $subscription;
        }

        $subscription = new Subscription($subscriber, $topic, $objectType, $objectId);
        $this->em->persist($subscription);
        $this->em->flush();

        return $subscription;
    }

    public function unsubscribe(string $userId, string $topic, ?string $objectType = null, ?string $objectId = null): void
    {
        $subscriber = $this->subscriberManager->find($userId);
        if (null === $subscriber) {
            return;
        }

        $subscription = $this->repository->findOneForObject($subscriber, $topic, $objectType, $objectId);
        if (null === $subscription) {
            return;
        }

        $this->em->remove($subscription);
        $this->em->flush();
    }

    public function isSubscribed(string $userId, string $objectType, string $objectId): bool
    {
        $subscriber = $this->subscriberManager->find($userId);
        if (null === $subscriber) {
            return false;
        }

        return null !== $this->repository->findOneForObject($subscriber, $objectType, $objectId);
    }

    /**
     * @return array<int, string>
     */
    public function getSubscriberUserIds(string $topic, string $objectType, string $objectId): array
    {
        return $this->repository->findSubscriberUserIds($objectType, $objectId);
    }
}
