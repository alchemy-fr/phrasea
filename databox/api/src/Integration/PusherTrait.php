<?php

declare(strict_types=1);

namespace App\Integration;

use Alchemy\CoreBundle\Pusher\PusherManager;
use App\Entity\Basket\Basket;
use App\Entity\Core\File;
use App\Realtime\RealtimeChannel;
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

    public function triggerExportPush(string $exportId, string $event, array $payload): void
    {
        $this->pusherManager->trigger(RealtimeChannel::export($exportId), $event, $payload, direct: true);
    }

    public function triggerFilePush(string $integrationName, File $file, array $payload, bool $direct = false): void
    {
        $this->pusherManager->trigger(RealtimeChannel::file($file->getId()), 'integration:'.$integrationName, $payload, $direct);
    }

    public function triggerBasketPush(string $integrationName, Basket $basket, array $payload, bool $direct = false): void
    {
        $this->pusherManager->trigger(RealtimeChannel::basket($basket->getId()), 'integration:'.$integrationName, $payload, $direct);
    }
}
