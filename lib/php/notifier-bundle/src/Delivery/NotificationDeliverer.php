<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Delivery;

use Alchemy\NotifierBundle\Channel\ChannelRegistry;
use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Manager\PreferenceManager;
use Alchemy\NotifierBundle\Manager\SubscriberManager;
use Alchemy\NotifierBundle\Topic\TopicRegistry;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Delivers a single topic notification to one subscriber across every enabled
 * channel of the topic. Each channel renders (or stores) the raw context on its
 * own. Channel failures are logged and do not abort delivery on the others.
 */
class NotificationDeliverer
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly SubscriberManager $subscriberManager,
        private readonly PreferenceManager $preferenceManager,
        private readonly ChannelRegistry $channelRegistry,
        private readonly TopicRegistry $topicRegistry,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param array<string, mixed>    $params
     * @param array<string, mixed>    $options
     * @param array<int, ChannelType> $channels Restricts delivery to those channels (null: every channel of the topic)
     */
    public function deliver(string $userId, string $topic, array $params = [], array $options = [], ?array $channels = null): void
    {
        $this->deliverTo($this->subscriberManager->getOrCreate($userId), $topic, $params, $options, $channels);
    }

    /**
     * Same as {@see deliver()} for an already resolved subscriber, so a bulk
     * sender does not pay a lookup per recipient.
     *
     * @param array<string, mixed>    $params
     * @param array<string, mixed>    $options
     * @param array<int, ChannelType> $channels
     */
    public function deliverTo(Subscriber $subscriber, string $topic, array $params = [], array $options = [], ?array $channels = null): void
    {
        $definition = $this->topicRegistry->get($topic);

        $context = $params + [
            'recipient' => [
                'userId' => $subscriber->getUserId(),
                'displayName' => $subscriber->getDisplayName(),
                'email' => $subscriber->getEmail(),
                'locale' => $subscriber->getLocale(),
            ],
        ];

        foreach ($definition->channels as $channelType) {
            if (null !== $channels && !in_array($channelType, $channels, true)) {
                continue;
            }

            if (!$this->channelRegistry->has($channelType)) {
                $this->logger->warning(sprintf('No channel implementation for "%s" (topic "%s").', $channelType->value, $topic));
                continue;
            }

            if (!$this->preferenceManager->isChannelEnabled($subscriber, $topic, $channelType)) {
                continue;
            }

            $channel = $this->channelRegistry->get($channelType);
            if (!$channel->supports($subscriber)) {
                continue;
            }

            try {
                $channel->send($subscriber, $topic, $context, $options);
            } catch (\Throwable $e) {
                $this->logger->error(sprintf('Failed to send topic "%s" on channel "%s" to user "%s": %s', $topic, $channelType->value, $subscriber->getUserId(), $e->getMessage()), [
                    'exception' => $e,
                ]);
            }
        }
    }
}
