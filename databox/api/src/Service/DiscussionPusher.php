<?php

namespace App\Service;

use Alchemy\CoreBundle\Pusher\PusherManager;
use App\Entity\Discussion\Message;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class DiscussionPusher
{
    public function __construct(
        private PusherManager $pusherManager,
        private SerializerInterface $serializer,
        private MessageBusInterface $bus,
    )
    {
    }

    public function dispatchMessageToThread(Message $message, bool $removed = false): void
    {
        $event = $removed ? 'message-delete' : 'message';

        $this->bus->dispatch($this->pusherManager->createBusMessage(
            'thread-'.$message->getThread()->getId(),
            $event,
            $removed ? [
                'id' => $message->getId(),
            ] : json_decode($this->serializer->serialize($message, 'json', [
                'groups' => [
                    '_',
                    Message::GROUP_READ,
                ],
            ]), true, 512, JSON_THROW_ON_ERROR),
        ));
    }
}
