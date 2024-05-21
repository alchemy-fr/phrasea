<?php

namespace App\Integration;

use Alchemy\CoreBundle\Pusher\PusherManager;
use Symfony\Contracts\Service\Attribute\Required;

trait PusherTrait
{
    private PusherManager $pusherManager;

    #[Required]
    public function setPusherManager(PusherManager $pusherManager): void
    {
        $this->pusherManager = $pusherManager;
    }

    public function triggerPush(string $channel, string $type, array $payload, bool $direct = false): void
    {
        $this->pusherManager->trigger($channel, $type, $payload, $direct);
    }
}
