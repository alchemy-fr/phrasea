<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Message;

/**
 * Asynchronous request to deliver a topic notification to a whole audience.
 *
 * The audience is named (see `UserDirectoryInterface`) rather than materialized
 * here: the recipient list is resolved in the worker, so the message stays small
 * and reflects the directory at delivery time.
 */
final readonly class BroadcastNotification
{
    /**
     * @param array<string, mixed>    $params
     * @param array<int, string>|null $channels Channel values the delivery is restricted to (null: every channel of the topic)
     */
    public function __construct(
        public string $topic,
        public array $params = [],
        public ?array $channels = null,
        public ?string $excludeUserId = null,
        public ?string $directory = null,
    ) {
    }
}
