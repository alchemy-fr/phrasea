<?php

namespace Alchemy\NotifyBundle\Message;

final readonly class NotifyTopic
{
    public function __construct(
        public string $topicKey,
        public ?string $authorId,
        public string $notificationId,
        public array $parameters = [],
        public array $options = [],
    ) {
    }
}
