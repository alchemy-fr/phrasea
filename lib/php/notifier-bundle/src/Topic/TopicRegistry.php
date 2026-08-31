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
     * @param array<string, array{channels: array<int, string>, importance: string, user_configurable: bool}> $topics
     */
    public function __construct(
        #[Autowire(param: 'alchemy_notifier.topics')]
        array $topics = [],
    ) {
        foreach ($topics as $key => $topic) {
            $this->topics[$key] = new TopicDefinition(
                $key,
                array_map(static fn (string $c): ChannelType => ChannelType::from($c), $topic['channels']),
                $topic['importance'],
                $topic['user_configurable'],
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
