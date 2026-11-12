<?php

declare(strict_types=1);

namespace App\Service\Discussion;

use Alchemy\CoreBundle\Pusher\PusherManager;
use App\Entity\Discussion\Message;
use App\Realtime\RealtimeChannel;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class DiscussionPusher
{
    public function __construct(
        private PusherManager $pusherManager,
        private MessageBusInterface $bus,
    ) {
    }

    public function dispatchMessageToThread(Message $message, bool $removed = false): void
    {
        $event = $removed ? 'message-delete' : 'message';

        $this->bus->dispatch($this->pusherManager->createBusMessage(
            RealtimeChannel::thread($message->getThread()->getKey()),
            $event,
            [
                'id' => $message->getId(),
            ],
        ));
    }
}
