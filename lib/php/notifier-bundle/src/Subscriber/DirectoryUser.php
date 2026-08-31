<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Subscriber;

/**
 * A notifiable user yielded by a {@see UserDirectoryInterface}.
 *
 * When the directory already knows the contact information (e.g. it listed the
 * users from Keycloak), it ships it along so the delivery pipeline does not
 * have to resolve it again one user at a time.
 */
final readonly class DirectoryUser
{
    public function __construct(
        public string $userId,
        public ?SubscriberInfo $info = null,
    ) {
    }
}
