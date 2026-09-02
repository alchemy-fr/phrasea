<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Digest;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Message\FlushNotificationDigest;
use Alchemy\NotifierBundle\Repository\NotificationDigestRepository;
use Alchemy\NotifierBundle\Topic\DigestConfig;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Entry point of the digest pipeline: buffers one event instead of delivering
 * it, and schedules the delayed flush probe when the event opened the bucket.
 */
class DigestBuffer
{
    private ClockInterface $clock;

    public function __construct(
        private readonly NotificationDigestRepository $repository,
        private readonly MessageBusInterface $bus,
        ?ClockInterface $clock = null,
    ) {
        $this->clock = $clock ?? new NativeClock();
    }

    /**
     * @param array<string, mixed> $params Raw topic params (not the recipient-augmented
     *                                     context: the recipient is rebuilt at flush
     *                                     time from the then-current Subscriber)
     */
    public function add(Subscriber $subscriber, string $topic, ChannelType $channel, array $params, DigestConfig $config): void
    {
        $now = $this->clock->now();

        ['id' => $id, 'inserted' => $inserted] = $this->repository->append(
            $subscriber->getId(),
            $topic,
            $channel->value,
            ['params' => $params, 'at' => $now->format(\DateTimeInterface::ATOM)],
            $now,
        );

        if ($inserted) {
            $this->bus->dispatch(
                new FlushNotificationDigest($id, $now->getTimestamp() + $config->inactivityDelay),
                [new DelayStamp($config->inactivityDelay * 1000)],
            );
        }
    }

    /**
     * Drops the pending digests of one topic for a subscriber — used when the
     * subscriber already saw the events (read the in-app notifications), so the
     * grouped email would be redundant. The in-flight flush probe then finds no
     * bucket and stops; a later event simply opens a fresh one.
     */
    public function discard(Subscriber $subscriber, string $topic): void
    {
        $this->repository->deleteBucketFor($subscriber->getId(), $topic);
    }

    /**
     * Same as {@see discard()} for every topic of the subscriber (read-all).
     */
    public function discardAll(Subscriber $subscriber): void
    {
        $this->repository->deleteAllFor($subscriber->getId());
    }
}
