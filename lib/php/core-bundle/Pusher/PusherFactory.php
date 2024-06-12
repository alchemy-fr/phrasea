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
            ])
        );
    }
}
