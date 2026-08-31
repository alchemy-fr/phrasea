<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Model;

use Alchemy\NotifierBundle\Channel\ChannelType;

final readonly class BroadcastOptions
{
    /**
     * @param array<int, ChannelType|string>|null $channels  Restricts delivery to those channels (null: every channel of the topic)
     * @param string|null                         $directory Audience to broadcast to (null: the configured default)
     */
    public function __construct(
        public ?array $channels = null,
        public ?string $excludeUserId = null,
        public ?string $directory = null,
    ) {
    }

    /**
     * @return array<int, string>|null
     */
    public function getChannelValues(): ?array
    {
        if (null === $this->channels) {
            return null;
        }

        return array_values(array_map(
            static fn (ChannelType|string $channel): string => $channel instanceof ChannelType ? $channel->value : $channel,
            $this->channels,
        ));
    }
}
