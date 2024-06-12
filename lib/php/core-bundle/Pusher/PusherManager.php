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
    )
    {
    }

    public function trigger(string $channel, string $event, array $payload, bool $direct = false): void
    {
        if ($direct) {
            $this->pusher->trigger($channel, $event, $payload);

            return;
        }

        $this->bus->dispatch(new PusherMessage($channel, $event, $payload));
    }
}
