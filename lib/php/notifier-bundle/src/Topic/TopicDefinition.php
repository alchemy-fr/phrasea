<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Topic;

use Alchemy\NotifierBundle\Channel\ChannelType;

final readonly class TopicDefinition
{
    /**
     * @param array<int, ChannelType> $channels
     */
    public function __construct(
        public string $key,
        public array $channels,
        public string $importance = 'normal',
        public bool $userConfigurable = true,
    ) {
    }
}
