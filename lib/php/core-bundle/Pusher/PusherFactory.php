<?php

namespace Alchemy\CoreBundle\Pusher;

use GuzzleHttp\Client;
use Pusher\Pusher;

abstract class PusherFactory
{
    public static function create(
        string $host,
        string $key,
        string $secret,
        string $appId,
        bool $verifySsl = true,
    ): Pusher {
        return new Pusher(
            $key,
            $secret,
            $appId,
            [
                'host' => $host,
            ],
            new Client([
                'verify' => $verifySsl,
                // Pushes are best-effort real-time hints: never let an unreachable
                // Soketi host hold a request or a worker for the default 30s+.
                'connect_timeout' => 3,
                'timeout' => 5,
            ])
        );
    }
}
