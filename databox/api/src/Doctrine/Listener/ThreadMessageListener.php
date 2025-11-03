<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Entity\Discussion\Message;
use App\Service\Discussion\DiscussionPusher;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(Events::preRemove)]
readonly class ThreadMessageListener implements EventSubscriber
{
    public function __construct(
        private DiscussionPusher $discussionPusher,
        private PostFlushStack $postFlushStack,
    ) {
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $object = $args->getObject();

        if ($object instanceof Message) {
            $this->postFlushStack->addCallback(function () use ($object): void {
                $this->discussionPusher->dispatchMessageToThread($object, removed: true);
            });
        }
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::preRemove,
        ];
    }
}
