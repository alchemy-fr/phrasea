<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Channel;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class ChannelRegistry
{
    public const string TAG = 'alchemy_notifier.channel';

    /**
     * @var array<string, ChannelInterface>
     */
    private array $channels = [];

    /**
     * @param iterable<ChannelInterface> $channels
     */
    public function __construct(
        #[AutowireIterator(self::TAG)]
        iterable $channels,
    ) {
        foreach ($channels as $channel) {
            $this->channels[$channel->getType()->value] = $channel;
        }
    }

    public function has(ChannelType $type): bool
    {
        return isset($this->channels[$type->value]);
    }

    public function get(ChannelType $type): ChannelInterface
    {
        return $this->channels[$type->value] ?? throw new \InvalidArgumentException(sprintf('No channel registered for type "%s".', $type->value));
    }
}
