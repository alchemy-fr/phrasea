<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Topic;

use Alchemy\NotifierBundle\Channel\ChannelType;

/**
 * Digest settings of a topic: instead of one delivery per event, the digested
 * channels buffer their events and send a single grouped notification once the
 * topic has been quiet for `inactivityDelay` seconds (every new event pushes
 * the send back), or at the latest `maxDelay` seconds after the first buffered
 * event.
 */
final readonly class DigestConfig
{
    /**
     * @param array<int, ChannelType> $channels Channels the digest applies to; the others deliver immediately
     * @param string                  $groupBy  Event param whose value groups the events into the
     *                                          `byObject` sections of the digest template
     */
    public function __construct(
        public int $inactivityDelay = 600,
        public int $maxDelay = 3600,
        public array $channels = [ChannelType::Email],
        public string $groupBy = 'objectId',
    ) {
    }

    public function applies(ChannelType $channel): bool
    {
        return in_array($channel, $this->channels, true);
    }
}
