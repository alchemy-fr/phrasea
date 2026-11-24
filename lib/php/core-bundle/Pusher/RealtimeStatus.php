<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Pusher;

/**
 * State of the Sockudo (Pusher compatible) application, as seen through its HTTP API.
 * A null count means that the server did not return it.
 */
final readonly class RealtimeStatus
{
    /**
     * @param array<string, array{channels: int, subscriptions: ?int}> $channelsByType
     * @param array<string, ?int>                                      $users          user ID => connections
     */
    public function __construct(
        public bool $enabled,
        public bool $reachable,
        public ?string $appId = null,
        public ?string $error = null,
        public ?float $latencyMs = null,
        public int $channelCount = 0,
        public ?int $subscriptionCount = null,
        public array $channelsByType = [],
        public array $users = [],
    ) {
    }
}
