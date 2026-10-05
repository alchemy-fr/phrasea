<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Message;

use Alchemy\NotifierBundle\Digest\DigestFlusher;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
final class FlushNotificationDigestHandler
{
    /**
     * Absorbs AMQP delay imprecision when checking whether the DelayStamp was
     * honored at all.
     */
    private const int GRACE_SECONDS = 30;

    private ClockInterface $clock;

    public function __construct(
        private readonly DigestFlusher $flusher,
        private readonly MessageBusInterface $bus,
        ?ClockInterface $clock = null,
    ) {
        $this->clock = $clock ?? new NativeClock();
    }

    public function __invoke(FlushNotificationDigest $message): void
    {
        $now = $this->clock->now();

        // Running way before the delay elapsed means the transport ignored the
        // DelayStamp (sync transport in dev). Rescheduling would then busy-loop
        // in-process forever: give up and let the fallback command
        // (alchemy:notifier:digest:flush) flush the bucket instead.
        if ($now->getTimestamp() < $message->notBefore - self::GRACE_SECONDS) {
            return;
        }

        if ($this->flusher->flushIfDue($message->digestId)) {
            return;
        }

        // Activity went on during the window: probe again at the next possible
        // due time (the max-delay cap is folded into the remaining delay).
        $remaining = $this->flusher->getRemainingDelay($message->digestId);
        if (null === $remaining) {
            return;
        }

        $this->bus->dispatch(
            new FlushNotificationDigest($message->digestId, $now->getTimestamp() + $remaining),
            [new DelayStamp($remaining * 1000)],
        );
    }
}
