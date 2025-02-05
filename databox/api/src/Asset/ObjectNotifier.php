<?php

namespace App\Asset;

use Alchemy\NotifyBundle\Notification\NotifierInterface;
use App\Entity\FollowableInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ObjectNotifier
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotifierInterface $notifier,
    ) {
    }

    public function notifyObject(
        FollowableInterface $object,
        string $event,
        string $notificationId,
        string $authorId,
        array $notificationParams,
    ): void {
        $notificationParams['author'] ??= $this->notifier->getUsername($authorId);

        $shouldNotify = true;

        $topicKey = $object::getTopicKey($event, $object->getId());

        if (!$object->novuTopicExists($topicKey)) {
            $shouldNotify = $this->em->wrapInTransaction(function () use ($object, $topicKey): bool {
                $this->em->lock($object, LockMode::PESSIMISTIC_WRITE);
                if ($object->novuTopicExists($topicKey)) {
                    return true;
                }

                if ($object->isAutoSubscribeOwner() && $object->getOwnerId()) {
                    $this->notifier->addTopicSubscribers($topicKey, [$object->getOwnerId()]);
                    $shouldNotify = true;
                } else {
                    $this->notifier->createTopic($topicKey);
                    $shouldNotify = false;
                }

                $object->setNovuTopicCreated($topicKey);
                $this->em->persist($object);

                return $shouldNotify;
            });
        }

        if ($shouldNotify) {
            $this->notifier->notifyTopic($topicKey, $authorId, $notificationId, $notificationParams);
        }
    }
}
