<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Message;

/**
 * Transactional email sent to a raw address (no subscriber / no preferences).
 *
 * Used for notifications to people who are not (yet) users of the system,
 * e.g. an anonymous download requester.
 */
final readonly class SendEmailNotification
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        public string $email,
        public string $topic,
        public array $params = [],
        public ?string $locale = null,
    ) {
    }
}
