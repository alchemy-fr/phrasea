<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Message;

use Alchemy\NotifierBundle\Model\NotifySelectorDto;
use Alchemy\NotifierBundle\Model\TopicDto;

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
     * @param array<int, NotifySelectorDto> $selectors
     * @param array<string, mixed>          $options
     */
    public function __construct(
        public array $selectors,
        public ?TopicDto $topic = null,
        public array $options = [],
        public ?string $excludeUserId = null,
    ) {
    }
}
