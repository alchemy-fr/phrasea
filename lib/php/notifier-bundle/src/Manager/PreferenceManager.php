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
     * subscriber has not explicitly opted out.
     */
    public function isChannelEnabled(Subscriber $subscriber, string $topic, ChannelType $channel): bool
    {
        if ($this->topicRegistry->has($topic)
            && !in_array($channel, $this->topicRegistry->get($topic)->channels, true)) {
            return false;
        }

        $preference = $this->repository->findOneForChannel($subscriber, $topic, $channel);

        return $preference?->isEnabled() ?? true;
    }

    public function setPreference(Subscriber $subscriber, string $topic, ChannelType $channel, bool $enabled): NotificationPreference
    {
        $preference = $this->repository->findOneForChannel($subscriber, $topic, $channel);
        if (null === $preference) {
            $preference = new NotificationPreference($subscriber, $topic, $channel, $enabled);
            $this->em->persist($preference);
        } else {
            $preference->setEnabled($enabled);
        }

        $this->em->flush();

        return $preference;
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
