<?php

namespace App\Integration;

use Alchemy\CoreBundle\Pusher\PusherManager;
use App\Entity\Core\File;
use Symfony\Contracts\Service\Attribute\Required;

trait PusherTrait
{
    private PusherManager $pusherManager;

    #[Required]
    public function setPusherManager(PusherManager $pusherManager): void
    {
        $this->pusherManager = $pusherManager;
    }

    public function triggerPush(string $channel, string $event, array $payload, bool $direct = false): void
    {
        $this->pusherManager->trigger($channel, $event, $payload, $direct);
    }

    public function triggerFilePush(string $integrationName, File $file, array $payload, bool $direct = false): void
    {
        $this->pusherManager->trigger('file-'.$file->getId(), 'integration:'.$integrationName, $payload, $direct);
    }
}
