<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Manager;

use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Repository\SubscriberRepository;
use Alchemy\NotifierBundle\Subscriber\SubscriberInfo;
use Alchemy\NotifierBundle\Subscriber\SubscriberInfoProviderInterface;
use Doctrine\ORM\EntityManagerInterface;

class SubscriberManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SubscriberRepository $repository,
        private readonly SubscriberInfoProviderInterface $infoProvider,
    ) {
    }

    public function find(string $userId): ?Subscriber
    {
        return $this->repository->findOneByUserId($userId);
    }

    /**
     * Returns the subscriber for the given userId, creating it (and resolving
     * its contact info) on first use.
     *
     * A caller that already holds the contact info (a bulk sender listing users
     * from the directory) passes it in to skip the per-user lookup; it also
     * keeps the locally cached info in sync on every send.
     */
    public function getOrCreate(string $userId, ?SubscriberInfo $info = null): Subscriber
    {
        $subscriber = $this->repository->findOneByUserId($userId);
        if (null !== $subscriber) {
            if (null !== $info) {
                $this->applyInfo($subscriber, $info);
                // No-op when nothing actually changed
                $this->em->flush();
            }

            return $subscriber;
        }

        $subscriber = new Subscriber($userId);
        $this->applyInfo($subscriber, $info ?? $this->infoProvider->getInfo($userId));

        $this->em->persist($subscriber);
        $this->em->flush();

        return $subscriber;
    }

    /**
     * Refreshes the cached contact info from the info provider.
     */
    public function refresh(Subscriber $subscriber): Subscriber
    {
        $this->applyInfo($subscriber, $this->infoProvider->getInfo($subscriber->getUserId()));
        $this->em->flush();

        return $subscriber;
    }

    private function applyInfo(Subscriber $subscriber, ?SubscriberInfo $info): void
    {
        if (null === $info) {
            return;
        }

        $subscriber->setEmail($info->email);
        $subscriber->setPhoneNumber($info->phoneNumber);
        $subscriber->setLocale($info->locale);
        $subscriber->setDisplayName($info->displayName);
    }
}
