<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Subscriber;

/**
 * Resolves contact information for a userId.
 *
 * The default implementation reads from Keycloak (auth-bundle), but the
 * application may decorate/replace this service to source data elsewhere.
 */
interface SubscriberInfoProviderInterface
{
    public function getInfo(string $userId): ?SubscriberInfo;
}
