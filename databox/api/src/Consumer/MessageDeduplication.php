<?php

declare(strict_types=1);

namespace App\Consumer;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;

/**
 * Wraps a message in an envelope that is dropped on dispatch while an identical one (same
 * class, same payload) is pending, through the DeduplicateMiddleware and its Redis lock.
 *
 * Only for messages routed to a transport: the lock is released when the message is
 * received, a message handled synchronously would keep it until the TTL expires and the
 * identical ones dispatched meanwhile would be lost.
 */
final class MessageDeduplication
{
    /**
     * Coalescing: one copy waits in the queue at most. The lock is released when the message
     * is received, so an identical message dispatched while it is being handled is queued.
     * For handlers reading the current state, whose result depends on when they run.
     */
    public static function coalesce(object $message, float $ttl = 3600): Envelope
    {
        return new Envelope($message, [
            new DeduplicateStamp(self::key($message), $ttl, onlyDeduplicateInQueue: true),
        ]);
    }

    /**
     * No identical message is queued while one is pending or being handled. For handlers
     * whose result only depends on immutable inputs: a change made meanwhile would be lost.
     */
    public static function untilHandled(object $message, float $ttl = 900): Envelope
    {
        return new Envelope($message, [
            new DeduplicateStamp(self::key($message), $ttl, onlyDeduplicateInQueue: false),
        ]);
    }

    private static function key(object $message): string
    {
        return 'dedup:'.$message::class.':'.sha1(serialize($message));
    }
}
