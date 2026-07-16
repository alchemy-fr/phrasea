<?php

namespace Alchemy\NotifierBundle\Model;

final readonly class NotifyTopicDto
{
    public function __construct(
        public string $topic,
        public ?string $objectType = null,
        public ?string $objectId = null,
        public array $userIds = [],
    ) {
    }
}
