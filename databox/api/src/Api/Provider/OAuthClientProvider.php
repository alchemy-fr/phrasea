<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Client\KeycloakClient;
use Alchemy\AuthBundle\Security\DataboxIntegrationClient;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\KeycloakTokenOwnerTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Traits\ParameterValuesTrait;
use App\Integration\Auth\OAuthRedirectUriMatcher;
use App\Model\OAuthClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The Keycloak clients an integration can authorize its users with: enabled, public, with the
 * standard flow. The ones allowing the redirect URI of the integration come first, then the ones
 * Expose accepts.
 */
final class OAuthClientProvider implements ProviderInterface
{
    use KeycloakTokenOwnerTrait;
    use ParameterValuesTrait;

    private const int MAX_CLIENTS = 500;

    /**
     * The clients Keycloak creates in every realm.
     */
    private const array BUILT_IN_CLIENT_IDS = [
        'account',
        'account-console',
        'admin-cli',
        'broker',
        'realm-management',
        'security-admin-console',
    ];

    /**
     * @param string[] $exposeAllowedClientIds the clients listed in the ALLOWED_CLIENT_IDS of the Expose API
     */
    public function __construct(
        private readonly KeycloakClient $keycloakClient,
        private readonly OAuthRedirectUriMatcher $redirectUriMatcher,
        #[Autowire('%env(csv:EXPOSE_ALLOWED_CLIENT_IDS)%')]
        private readonly array $exposeAllowedClientIds,
    ) {
    }

    /**
     * @return OAuthClient[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->getKeycloakTokenOwner(JwtUser::ROLE_KEYCLOAK_VIEW_CLIENTS);

        $parameters = self::getParameterValues($operation);
        $integrationId = $parameters['integrationId'] ?? null;
        if (null !== $integrationId && (!is_string($integrationId) || 1 !== preg_match('#^[A-Za-z0-9-]{1,64}$#', $integrationId))) {
            throw new BadRequestHttpException('Invalid "integrationId"');
        }

        $query = [
            'max' => self::MAX_CLIENTS,
        ];
        $search = trim((string) ($parameters['search'] ?? ''));
        if ('' !== $search) {
            $query['clientId'] = $search;
            $query['search'] = 'true';
        }

        $target = $this->redirectUriMatcher->getRedirectUri($integrationId);
        $clients = [];
        foreach ($this->keycloakClient->getClients($user->getJwt(), ['query' => $query]) as $data) {
            if (self::isCompatible($data)) {
                $clients[] = $this->createClient($data, $target);
            }
        }

        usort($clients, fn (OAuthClient $a, OAuthClient $b): int => [!$a->redirectUriAllowed, !$a->acceptedByExpose, $a->clientId] <=> [!$b->redirectUriAllowed, !$b->acceptedByExpose, $b->clientId]);

        return $clients;
    }

    private static function isCompatible(array $data): bool
    {
        return true === ($data['enabled'] ?? false)
            && true === ($data['publicClient'] ?? false)
            && true === ($data['standardFlowEnabled'] ?? false)
            && true !== ($data['bearerOnly'] ?? false)
            && !in_array($data['clientId'] ?? null, self::BUILT_IN_CLIENT_IDS, true);
    }

    public function createClient(array $data, string $target): OAuthClient
    {
        $client = new OAuthClient();
        $client->id = $data['id'] ?? null;
        $client->clientId = (string) ($data['clientId'] ?? '');
        $client->name = !empty($data['name']) ? $data['name'] : null;
        $client->description = !empty($data['description']) ? $data['description'] : null;
        $client->redirectUris = array_values(array_filter($data['redirectUris'] ?? [], is_string(...)));
        $client->redirectUriAllowed = OAuthRedirectUriMatcher::isAllowed($target, $client->redirectUris, $data['rootUrl'] ?? null);
        // The clients of the integrations have their redirect URIs under the root URL of the API
        $client->acceptedByExpose = in_array($client->clientId, array_map(trim(...), $this->exposeAllowedClientIds), true)
            || DataboxIntegrationClient::isAccepted($data, $this->redirectUriMatcher->getClientRootUrl());

        return $client;
    }
}
