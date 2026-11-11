<?php

declare(strict_types=1);

namespace App\Security;

use Alchemy\AuthBundle\Client\KeycloakClient;
use Alchemy\AuthBundle\Client\ServiceAccountClient;
use Alchemy\AuthBundle\Security\AllowedClientIdsProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * The OAuth clients Databox creates for its integrations (Keycloak attribute "phrasea.created_by"
 * set to "databox" by its CreateOAuthClientProcessor): the Expose integration of Databox calls this
 * API with the tokens its users obtain from them.
 *
 * Each client is looked up in Keycloak with the service account (it needs the "view-clients" role
 * of "realm-management"), and the answer cached for a short time. A client cannot appear in a
 * signed token before its creation, so no cache entry has to be invalidated when Databox creates one.
 */
final readonly class DataboxIntegrationClientIds implements AllowedClientIdsProviderInterface
{
    final public const string CREATED_BY_ATTRIBUTE = 'phrasea.created_by';
    final public const string CREATED_BY = 'databox';

    private const int CACHE_TTL = 60;

    /**
     * Keeps Keycloak from being queried on each request while it fails.
     */
    private const int ERROR_CACHE_TTL = 30;

    public function __construct(
        private ServiceAccountClient $serviceAccountClient,
        private KeycloakClient $keycloakClient,
        #[Target]
        private CacheInterface $keycloakRealmCache,
        private LoggerInterface $logger,
    ) {
    }

    public function isClientAllowed(string $clientId): bool
    {
        return $this->keycloakRealmCache->get('databox_integration_client_'.hash('xxh128', $clientId), function (ItemInterface $item) use ($clientId): bool {
            $item->expiresAfter(self::CACHE_TTL);

            try {
                $clients = $this->serviceAccountClient->executeWithAccessToken(fn (string $accessToken): array => $this->keycloakClient->getClients($accessToken, [
                    'query' => [
                        'clientId' => $clientId,
                        'search' => 'false',
                        'max' => 1,
                    ],
                ]));
            } catch (\Exception $e) {
                $this->logger->error('Cannot check whether the OAuth client "{client_id}" was created by Databox: {error}', [
                    'client_id' => $clientId,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
                $item->expiresAfter(self::ERROR_CACHE_TTL);

                return false;
            }

            foreach ($clients as $client) {
                if ($clientId === ($client['clientId'] ?? null)
                    && true === ($client['enabled'] ?? false)
                    && self::CREATED_BY === ($client['attributes'][self::CREATED_BY_ATTRIBUTE] ?? null)) {
                    return true;
                }
            }

            return false;
        });
    }
}
