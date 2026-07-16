<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Message;

use Alchemy\NotifierBundle\Model\NotifyTopicDto;

/**
 * Asynchronous request to deliver a topic notification.
 *
 * Recipients are given either explicitly (userIds) and/or through a followed
 * object (topic + objectType + objectId); resolution happens in the handler.
 *
 * $topics is an array and will prevent sending two different topics to the same subscriber.
 */
final readonly class SendNotification
{
    /**
     * @param array<int, NotifyTopicDto> $topics
     * @param array<string, mixed>       $params
     * @param array<string, mixed>       $options
     */
    public function __construct(
        public array $topics,
        public array $params = [],
        public array $options = [],
        public ?string $excludeUserId = null,
    ) {
    }
}
