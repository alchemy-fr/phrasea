<?php

declare(strict_types=1);

namespace App\Service\Asset;

use Alchemy\NotifierBundle\Manager\NotifierManager;
use Alchemy\NotifierBundle\Model\NotifyOptions;
use Alchemy\NotifierBundle\Model\NotifySelectorDto;
use Alchemy\NotifierBundle\Model\TopicDto;
use Alchemy\NotifyBundle\Notification\NotifierInterface;
use App\Entity\FollowableInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ObjectNotifier
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotifierInterface $notifier,
        private NotifierManager $notifierManager,
    ) {
    }

    public function notifyObject(
        FollowableInterface $object,
        string $event,
        string $notificationId,
        string $authorId,
        array $notificationParams,
        array $notificationOptions = [],
    ): void {
        $notificationParams['author'] ??= $this->notifier->getUsername($authorId);
        $notificationParams['authorId'] ??= $authorId;

        $shouldNotify = true;

        $topicKey = $object::getTopicKey($event);

        if (!$object->novuTopicExists($topicKey)) {
            $shouldNotify = $this->em->wrapInTransaction(function () use ($object, $topicKey): bool {
                $this->em->lock($object, LockMode::PESSIMISTIC_WRITE);
                if ($object->novuTopicExists($topicKey)) {
                    return true;
                }

                if ($object->isAutoSubscribeOwner() && $object->getOwnerId()) {
                    $this->notifier->addTopicSubscribers($topicKey, [$object->getOwnerId()], direct: true);
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
            $this->notifierManager->notify(
                [
                    new NotifySelectorDto(
                        event: $event,
                        objectType: $object::OBJECT_TYPE,
                        objectId: $object->getId(),
                        topic: new TopicDto($notificationId, $notificationParams),
                    ),
                ],
                new NotifyOptions(excludeUserId: $authorId),
            );
            $this->notifier->notifyTopic($topicKey, $authorId, $notificationId, $notificationParams, $notificationOptions);
        }
    }
}
