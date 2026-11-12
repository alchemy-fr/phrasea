<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

/**
 * OAuth clients whose access tokens the API accepts on top of the static list ("allowed_client_ids"
 * of the bundle), for clients known at runtime only (e.g. created by an application).
 *
 * Every service implementing it is a provider (autoconfiguration).
 */
interface AllowedClientIdsProviderInterface
{
    final public const string TAG = 'alchemy_auth.allowed_client_ids_provider';

    /**
     * Called for a client absent from the static list, once the token is otherwise valid
     * (signature, issuer, dates, type): the client ID comes from a token the realm signed.
     */
    public function isClientAllowed(string $clientId): bool;
}
