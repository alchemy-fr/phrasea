<?php

namespace Alchemy\NotifierBundle\Model;

final readonly class NotifySelectorDto
{
    public function __construct(
        public ?string $event = null,
        public ?string $objectType = null,
        public ?string $objectId = null,
        public array $userIds = [],
        // If null, parent will be used
        public ?TopicDto $topic = null,
    ) {
        if (!$this->event && empty($this->userIds)) {
            throw new \InvalidArgumentException('You must supply at least one user or an event');
        }
    }
}
