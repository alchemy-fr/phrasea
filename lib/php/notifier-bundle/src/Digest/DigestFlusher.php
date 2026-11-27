<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Digest;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Channel\EmailChannel;
use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Manager\PreferenceManager;
use Alchemy\NotifierBundle\Notification\NotificationRenderer;
use Alchemy\NotifierBundle\Notification\RenderedContent;
use Alchemy\NotifierBundle\NotifierState;
use Alchemy\NotifierBundle\Repository\NotificationDigestRepository;
use Alchemy\NotifierBundle\Topic\DigestConfig;
use Alchemy\NotifierBundle\Topic\TopicRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\NativeClock;

/**
 * Turns a due digest bucket into one grouped notification.
 *
 * The bucket is claimed with an atomic conditional DELETE inside a transaction:
 * a retried flush finds nothing (no duplicate email), a mailer failure rolls
 * the row back for the Messenger retry, and an event appended concurrently
 * simply opens a fresh bucket with its own probe.
 *
 * A bucket whose topic this process does not declare is not flushed right
 * away: during a rolling deploy, a worker still running the previous release
 * may receive the probe of a topic added by the new one, and would otherwise
 * drop the buffer it cannot render. Such a bucket is probed again every
 * UNKNOWN_TOPIC_RETRY seconds, so that an up-to-date worker sends it, and only
 * discarded UNKNOWN_TOPIC_GRACE seconds after it opened (topic removed from
 * the configuration).
 */
class DigestFlusher
{
    public const int UNKNOWN_TOPIC_GRACE = 3600;
    public const int UNKNOWN_TOPIC_RETRY = 60;

    private ClockInterface $clock;
    private LoggerInterface $logger;

    public function __construct(
        private readonly NotificationDigestRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly TopicRegistry $topicRegistry,
        private readonly PreferenceManager $preferenceManager,
        private readonly NotificationRenderer $renderer,
        private readonly EmailChannel $emailChannel,
        private readonly NotifierState $state,
        ?ClockInterface $clock = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->clock = $clock ?? new NativeClock();
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @return bool true when there is nothing left to wait for (flushed,
     *              discarded, or bucket gone); false when the bucket is not due
     *              yet and the probe must be rescheduled
     */
    public function flushIfDue(string $digestId): bool
    {
        $row = $this->repository->findRow($digestId);
        if (null === $row) {
            return true;
        }

        return $this->claimAndSend($digestId, (string) $row['topic'], force: false);
    }

    /**
     * Flushes every due bucket (every open bucket with $force). Safe to run
     * concurrently with the workers: each bucket is claimed atomically.
     *
     * @return int number of buckets flushed
     */
    public function flushOverdue(bool $force = false): int
    {
        $flushed = 0;
        foreach ($this->repository->findAllIds() as $id) {
            $row = $this->repository->findRow($id);
            if (null === $row) {
                continue;
            }
            if ($this->claimAndSend($id, (string) $row['topic'], $force)) {
                ++$flushed;
            }
        }

        return $flushed;
    }

    /**
     * Seconds until the bucket can possibly be due (next inactivity boundary,
     * capped by the max delay), or null when the bucket no longer exists.
     */
    public function getRemainingDelay(string $digestId): ?int
    {
        $row = $this->repository->findRow($digestId);
        if (null === $row) {
            return null;
        }

        $topic = (string) $row['topic'];
        $known = $this->topicRegistry->has($topic);
        $config = $known ? $this->getDigestConfig($topic) ?? new DigestConfig() : $this->getUnknownTopicConfig();
        $dueAt = min(
            new \DateTimeImmutable((string) $row['last_event_at'])->getTimestamp() + $config->inactivityDelay,
            new \DateTimeImmutable((string) $row['first_event_at'])->getTimestamp() + $config->maxDelay,
        );
        $remaining = max(1, $dueAt - $this->clock->now()->getTimestamp());

        return $known ? $remaining : min($remaining, self::UNKNOWN_TOPIC_RETRY);
    }

    private function getDigestConfig(string $topic): ?DigestConfig
    {
        return $this->topicRegistry->has($topic) ? $this->topicRegistry->get($topic)->digest : null;
    }

    private function getUnknownTopicConfig(): DigestConfig
    {
        return new DigestConfig(self::UNKNOWN_TOPIC_GRACE, self::UNKNOWN_TOPIC_GRACE);
    }

    /**
     * @return bool true when done with the bucket, false when not due yet
     */
    private function claimAndSend(string $digestId, string $topic, bool $force): bool
    {
        $known = $this->topicRegistry->has($topic);
        if ($known) {
            $config = $this->getDigestConfig($topic);
            // A topic whose digest config disappeared must not strand its buffer
            $force = $force || null === $config;
            $config ??= new DigestConfig();
        } else {
            $config = $this->getUnknownTopicConfig();
        }

        return (bool) $this->em->getConnection()->transactional(function () use ($digestId, $topic, $config, $force, $known): bool {
            $row = $this->repository->claimIfDue($digestId, $this->clock->now(), $config->inactivityDelay, $config->maxDelay, $force);
            if (null === $row) {
                return false;
            }

            if (!$known) {
                $this->logger->warning(sprintf('Discarding digest of topic "%s" (%d buffered): the topic is not declared under alchemy_notifier.topics.', $topic, (int) $row['event_count']));

                return true;
            }

            if (!$this->state->isEnabled()) {
                $this->logger->info(sprintf('Discarding digest of topic "%s": notifications are globally disabled.', $topic));

                return true;
            }

            $subscriber = $this->em->find(Subscriber::class, (string) $row['subscriber_id']);
            if (null === $subscriber) {
                return true;
            }

            $channel = ChannelType::from((string) $row['channel']);
            if (ChannelType::Email !== $channel) {
                $this->logger->warning(sprintf('Discarding digest of topic "%s": channel "%s" cannot deliver digests.', $topic, $channel->value));

                return true;
            }

            // The subscriber may have opted out (or lost their email) while the
            // window was open: the buffer is then simply dropped.
            if (!$this->preferenceManager->isChannelEnabled($subscriber, $topic, $channel)
                || !$this->emailChannel->supports($subscriber)) {
                return true;
            }

            $content = $this->render($row, $subscriber, $config);
            if (null !== $content) {
                $this->emailChannel->sendTo($subscriber->getEmail(), $topic, $content, locale: $subscriber->getLocale());
            }

            return true;
        });
    }

    /**
     * @param array<string, mixed> $row
     */
    private function render(array $row, Subscriber $subscriber, DigestConfig $config): ?RenderedContent
    {
        $topic = (string) $row['topic'];
        $locale = $subscriber->getLocale();
        $count = (int) $row['event_count'];

        /** @var array<int, array{params: array<string, mixed>, at: string}> $events */
        $events = json_decode((string) $row['events'], true, 512, JSON_THROW_ON_ERROR);
        if ([] === $events) {
            return null;
        }

        $recipient = [
            'userId' => $subscriber->getUserId(),
            'displayName' => $subscriber->getDisplayName(),
            'email' => $subscriber->getEmail(),
            'locale' => $locale,
        ];

        // A digest of one is just the notification itself
        if (1 === $count) {
            return $this->renderer->render($topic, ChannelType::Email, $events[0]['params'] + ['recipient' => $recipient], $locale);
        }

        if ($this->renderer->hasDigestTemplate($topic, ChannelType::Email)) {
            return $this->renderer->renderDigest($topic, ChannelType::Email, $this->buildDigestContext($row, $events, $recipient, $config->groupBy), $locale);
        }

        // Misconfiguration (digest enabled, no digest template): one email of
        // the latest event still beats a lost notification or an email blast.
        $this->logger->error(sprintf('Topic "%s" is digested but has no email digest template; sending the latest event only (%d buffered).', $topic, $count));

        return $this->renderer->render($topic, ChannelType::Email, $events[array_key_last($events)]['params'] + ['recipient' => $recipient], $locale);
    }

    /**
     * @param array<string, mixed>                                        $row
     * @param array<int, array{params: array<string, mixed>, at: string}> $events
     * @param array<string, mixed>                                        $recipient
     *
     * @return array<string, mixed>
     */
    private function buildDigestContext(array $row, array $events, array $recipient, string $groupBy): array
    {
        $decoded = array_map(static fn (array $event): array => [
            'params' => $event['params'],
            'at' => new \DateTimeImmutable($event['at']),
        ], $events);

        // One section per object in the email (comments grouped by asset, ...)
        $byObject = [];
        foreach ($decoded as $event) {
            $byObject[(string) ($event['params'][$groupBy] ?? '_')][] = $event;
        }

        $count = (int) $row['event_count'];

        return [
            'events' => $decoded,
            'count' => $count,
            'overflowCount' => max(0, $count - count($decoded)),
            'byObject' => $byObject,
            'firstEventAt' => new \DateTimeImmutable((string) $row['first_event_at']),
            'lastEventAt' => new \DateTimeImmutable((string) $row['last_event_at']),
            'recipient' => $recipient,
        ];
    }
}
