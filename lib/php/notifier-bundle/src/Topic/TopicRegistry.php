<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Topic;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class TopicRegistry
{
    /**
     * @var array<string, TopicDefinition>
     */
    private array $topics = [];

    /**
     * @param array<string, array{channels: array<int, string>, importance: string, user_configurable: bool, digest?: array{inactivity_delay: int, max_delay: int, channels: array<int, string>, group_by?: string}|null}> $topics
     */
    public function __construct(
        #[Autowire(param: 'alchemy_notifier.topics')]
        array $topics = [],
    ) {
        foreach ($topics as $key => $topic) {
            $digest = $topic['digest'] ?? null;

            $this->topics[$key] = new TopicDefinition(
                $key,
                array_map(static fn (string $c): ChannelType => ChannelType::from($c), $topic['channels']),
                $topic['importance'],
                $topic['user_configurable'],
                null !== $digest ? new DigestConfig(
                    $digest['inactivity_delay'],
                    $digest['max_delay'],
                    array_map(static fn (string $c): ChannelType => ChannelType::from($c), $digest['channels']),
                    $digest['group_by'] ?? 'objectId',
                ) : null,
            );
        }
    }

    public function has(string $key): bool
    {
        return isset($this->topics[$key]);
    }

    public function get(string $key): TopicDefinition
    {
        return $this->topics[$key] ?? throw new \InvalidArgumentException(sprintf('Unknown notification topic "%s". Declare it under alchemy_notifier.topics.', $key));
    }

    /**
     * @return array<string, TopicDefinition>
     */
    public function all(): array
    {
        return $this->topics;
    }
}
