<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Message;

/**
 * Asynchronous request to run the broadcast recorded under `$broadcastId`.
 *
 * The message carries nothing but that id: the topic, payload, channels and
 * audience are read back from the `notifier_broadcast` row, which stays the
 * single source of truth for what was sent.
 */
final readonly class BroadcastNotification
{
    public function __construct(
        public string $broadcastId,
    ) {
    }
}
