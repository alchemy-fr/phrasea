<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Subscriber;

/**
 * Contact information resolved for a userId (e.g. from Keycloak).
 */
final readonly class SubscriberInfo
{
    public function __construct(
        public ?string $email = null,
        public ?string $phoneNumber = null,
        public ?string $locale = null,
        public ?string $displayName = null,
    ) {
    }
}
