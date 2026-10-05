<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Repository;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Entity\NotificationPreference;
use Alchemy\NotifierBundle\Entity\Subscriber;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationPreference>
 */
class NotificationPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationPreference::class);
    }

    public function findOneForChannel(Subscriber $subscriber, string $topic, ChannelType $channel): ?NotificationPreference
    {
        return $this->findOneBy([
            'subscriber' => $subscriber,
            'topic' => $topic,
            'channel' => $channel,
        ]);
    }

    /**
     * @return array<int, NotificationPreference>
     */
    public function findForSubscriber(Subscriber $subscriber): array
    {
        return $this->findBy(['subscriber' => $subscriber]);
    }
}
