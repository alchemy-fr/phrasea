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
        ?string $port = null,
        ?string $scheme = null,
    ): Pusher {
        $options = [
            'host' => $host,
        ];

        // Server side, Soketi can be reached directly instead of through the
        // public host: without both of them, the client falls back to TLS on
        // port 443.
        if (null !== $port && '' !== $port) {
            $options['port'] = (int) $port;
        }
        if (null !== $scheme && '' !== $scheme) {
            $options['scheme'] = $scheme;
        }

        return new Pusher(
            $key,
            $secret,
            $appId,
            $options,
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
