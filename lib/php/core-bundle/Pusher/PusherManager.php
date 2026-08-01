<?php

namespace Alchemy\CoreBundle\Pusher;

use Alchemy\CoreBundle\Message\PusherMessage;
use Pusher\Pusher;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class PusherManager
{
    public function __construct(
        private Pusher $pusher,
        private MessageBusInterface $bus,
        private bool $disabled = false,
    ) {
    }

    public function trigger(string $channel, string $event, array $payload, bool $direct = false): void
    {
        if ($direct) {
            if (!$this->disabled) {
                $this->pusher->trigger($this->normalizeChannel($channel), $event, $payload);
            }

            return;
        }

        $this->bus->dispatch($this->createBusMessage($channel, $event, $payload));
    }

    /**
     * Returns the Pusher/Soketi authorization signature for a private (or
     * presence) channel subscription. The caller is responsible for verifying
     * that the current user is actually allowed to subscribe to $channel.
     */
    public function authorizeChannel(string $channel, string $socketId): string
    {
        return $this->pusher->authorizeChannel($channel, $socketId);
    }

    public function normalizeChannel(string $channel): string
    {
        return preg_replace('/[^a-z0-9_\-=@,.;]/i', '.', $channel);
    }

    public function createBusMessage(string $channel, string $event, array $payload): PusherMessage
    {
        return new PusherMessage($channel, $event, $payload);
    }
}
