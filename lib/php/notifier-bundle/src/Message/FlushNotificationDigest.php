<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Message;

/**
 * Delayed probe asking whether the digest bucket may be flushed. One probe is
 * dispatched when the bucket is created; when it wakes up too early (activity
 * went on), it reschedules itself for the next possible due time.
 */
final readonly class FlushNotificationDigest
{
    /**
     * @param int $notBefore Unix timestamp the probe was delayed to. When the
     *                       handler runs earlier, the transport ignored the
     *                       DelayStamp (sync transport): the probe then gives
     *                       up instead of rescheduling in a busy loop.
     */
    public function __construct(
        public string $digestId,
        public int $notBefore,
    ) {
    }
}
