<?php

namespace Alchemy\CoreBundle\Pusher;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
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
            // Pusher passes its own timeout (30s by default) to every request,
            // which overrides the one of the Guzzle client below.
            'timeout' => 2,
        ];

        // Server side, Sockudo can be reached directly instead of through the
        // public host: without both of them, the client falls back to TLS on
        // port 443.
        if (null !== $port && '' !== $port) {
            $options['port'] = (int) $port;
        }
        if (null !== $scheme && '' !== $scheme) {
            $options['scheme'] = $scheme;
        }

        // After a network failure, the requests of the process are skipped for a
        // while instead of each waiting for the timeout.
        $stack = HandlerStack::create();
        $stack->push(new CircuitBreakerMiddleware(), 'circuit_breaker');

        return new Pusher(
            $key,
            $secret,
            $appId,
            $options,
            new Client([
                'verify' => $verifySsl,
                // Pushes are best-effort real-time hints and Sockudo answers in a
                // few milliseconds on the internal network: never let an
                // unreachable host hold a request or a worker for long.
                'connect_timeout' => 1,
                'timeout' => 2,
                'handler' => $stack,
            ])
        );
    }
}
