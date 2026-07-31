<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Message;

/**
 * Asynchronous request to deliver a topic notification to every subscriber.
 */
final readonly class BroadcastNotification
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        public string $topic,
        public array $params = [],
    ) {
    }
}
