<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Message;

/**
 * Asynchronous request to deliver a topic notification.
 *
 * Recipients are given either explicitly (userIds) and/or through a followed
 * object (objectType + objectId); resolution happens in the handler.
 */
final readonly class SendNotification
{
    /**
     * @param array<int, string>   $userIds
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     */
    public function __construct(
        public string $topic,
        public array $userIds = [],
        public ?string $objectType = null,
        public ?string $objectId = null,
        public array $params = [],
        public array $options = [],
        public ?string $excludeUserId = null,
    ) {
    }
}
