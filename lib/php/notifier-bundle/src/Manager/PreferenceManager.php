<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Manager;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Entity\NotificationPreference;
use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Repository\NotificationPreferenceRepository;
use Alchemy\NotifierBundle\Topic\TopicRegistry;
use Doctrine\ORM\EntityManagerInterface;

final class PreferenceManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationPreferenceRepository $repository,
        private readonly TopicRegistry $topicRegistry,
    ) {
    }

    /**
     * A channel is enabled when the topic delivers through it and the
     * subscriber has not explicitly opted out. Topics that are not
     * user-configurable ignore any stored preference.
     */
    public function isChannelEnabled(Subscriber $subscriber, string $topic, ChannelType $channel): bool
    {
        if ($this->topicRegistry->has($topic)) {
            $definition = $this->topicRegistry->get($topic);
            if (!in_array($channel, $definition->channels, true)) {
                return false;
            }
            if (!$definition->userConfigurable) {
                return true;
            }
        }

        $preference = $this->repository->findOneForChannel($subscriber, $topic, $channel);

        return $preference?->isEnabled() ?? true;
    }

    /**
     * Writes several preferences in a single flush. Every entry is validated
     * before anything is written; for duplicate (topic, channel) pairs the
     * last entry wins.
     *
     * @param list<array{topic: string, channel: ChannelType, enabled: bool}> $preferences
     *
     * @throws \InvalidArgumentException when a topic is unknown, not user-configurable or not delivered through the channel
     */
    public function setPreferences(Subscriber $subscriber, array $preferences): void
    {
        foreach ($preferences as $preference) {
            $this->assertConfigurable($preference['topic'], $preference['channel']);
        }

        $indexed = [];
        foreach ($this->repository->findForSubscriber($subscriber) as $existing) {
            $indexed[$existing->getTopic().'/'.$existing->getChannel()->value] = $existing;
        }

        foreach ($preferences as ['topic' => $topic, 'channel' => $channel, 'enabled' => $enabled]) {
            $key = $topic.'/'.$channel->value;
            if (isset($indexed[$key])) {
                $indexed[$key]->setEnabled($enabled);
            } else {
                $indexed[$key] = new NotificationPreference($subscriber, $topic, $channel, $enabled);
                $this->em->persist($indexed[$key]);
            }
        }

        $this->em->flush();
    }

    private function assertConfigurable(string $topic, ChannelType $channel): void
    {
        if (!$this->topicRegistry->has($topic)) {
            throw new \InvalidArgumentException('Unknown notification topic.');
        }

        $definition = $this->topicRegistry->get($topic);
        if (!$definition->userConfigurable) {
            throw new \InvalidArgumentException(sprintf('Notification topic "%s" cannot be configured.', $topic));
        }
        if (!in_array($channel, $definition->channels, true)) {
            throw new \InvalidArgumentException(sprintf('Notification topic "%s" is not delivered through channel "%s".', $topic, $channel->value));
        }
    }

    /**
     * Returns the effective preferences for a subscriber across all
     * user-configurable topics and their channels.
     *
     * @return array<int, array{topic: string, channel: string, enabled: bool}>
     */
    public function getEffectivePreferences(Subscriber $subscriber): array
    {
        $overrides = [];
        foreach ($this->repository->findForSubscriber($subscriber) as $preference) {
            $overrides[$preference->getTopic().'/'.$preference->getChannel()->value] = $preference->isEnabled();
        }

        $result = [];
        foreach ($this->topicRegistry->all() as $topic) {
            if (!$topic->userConfigurable) {
                continue;
            }
            foreach ($topic->channels as $channel) {
                $key = $topic->key.'/'.$channel->value;
                $result[] = [
                    'topic' => $topic->key,
                    'channel' => $channel->value,
                    'enabled' => $overrides[$key] ?? true,
                ];
            }
        }

        return $result;
    }
}
