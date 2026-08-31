<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Model;

use Alchemy\NotifierBundle\Channel\ChannelType;

final readonly class BroadcastOptions
{
    /**
     * @param array<int, ChannelType|string>|null $channels        Restricts delivery to those channels (null: every channel of the topic)
     * @param string|null                         $directory       Audience to broadcast to (null: the configured default)
     * @param string|null                         $initiatorUserId userId recorded as the sender (null: the currently authenticated user, if any)
     */
    public function __construct(
        public ?array $channels = null,
        public ?string $excludeUserId = null,
        public ?string $directory = null,
        public ?string $initiatorUserId = null,
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
