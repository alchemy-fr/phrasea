<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Channel;

use Alchemy\CoreBundle\Pusher\PusherManager;
use Alchemy\NotifierBundle\Entity\Notification;
use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Notification\RenderedContent;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persists an in-app notification and pushes a real-time event through Pusher.
 */
final readonly class InAppChannel implements ChannelInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private PusherManager $pusherManager,
        private string $channelPrefix = 'private-user-',
        private string $event = 'notification',
    ) {
    }

    public function getType(): ChannelType
    {
        return ChannelType::InApp;
    }

    public function supports(Subscriber $subscriber): bool
    {
        return true;
    }

    public function send(
        Subscriber $subscriber,
        string $topic,
        RenderedContent $content,
        array $context = [],
        array $options = [],
    ): void {
        $notification = new Notification($subscriber, $topic);
        $notification->setSubject($content->subject);
        $notification->setContent($content->body);
        $notification->setData($context);

        $this->em->persist($notification);
        $this->em->flush();

        $this->pusherManager->trigger(
            $this->channelPrefix.$subscriber->getUserId(),
            $this->event,
            [
                'id' => $notification->getId(),
                'topic' => $topic,
                'subject' => $content->subject,
                'content' => $content->body,
                'data' => $context,
            ],
        );
    }
}
