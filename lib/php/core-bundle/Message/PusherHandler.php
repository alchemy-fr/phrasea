<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Message;

use Pusher\Pusher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class PusherHandler
{
    public function __construct(private Pusher $pusher)
    {
    }

    public function __invoke(PusherMessage $message): void
    {
        $this->pusher->trigger($message->getChannel(), $message->getEvent(), $message->getChannel());
    }
}
