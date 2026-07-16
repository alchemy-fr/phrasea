<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Manager;

use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Repository\SubscriberRepository;
use Alchemy\NotifierBundle\Subscriber\SubscriberInfoProviderInterface;
use Doctrine\ORM\EntityManagerInterface;

final class SubscriberManager
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
     */
    public function getOrCreate(string $userId): Subscriber
    {
        $subscriber = $this->repository->findOneByUserId($userId);
        if (null !== $subscriber) {
            return $subscriber;
        }

        $subscriber = new Subscriber($userId);
        $this->applyInfo($subscriber);

        $this->em->persist($subscriber);
        $this->em->flush();

        return $subscriber;
    }

    /**
     * Refreshes the cached contact info from the info provider.
     */
    public function refresh(Subscriber $subscriber): Subscriber
    {
        $this->applyInfo($subscriber);
        $this->em->flush();

        return $subscriber;
    }

    private function applyInfo(Subscriber $subscriber): void
    {
        $info = $this->infoProvider->getInfo($subscriber->getUserId());
        if (null === $info) {
            return;
        }

        $subscriber->setEmail($info->email);
        $subscriber->setPhoneNumber($info->phoneNumber);
        $subscriber->setLocale($info->locale);
        $subscriber->setDisplayName($info->displayName);
    }
}
