<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Client\KeycloakClient;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\KeycloakTokenOwnerTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Traits\ParameterValuesTrait;
use App\Integration\Auth\OAuthRedirectUriMatcher;
use App\Model\OAuthClient;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The Keycloak clients an integration can authorize its users with: enabled, public, with the
 * standard flow. The ones allowing the redirect URI of the integration come first.
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

    public function __construct(
        private readonly KeycloakClient $keycloakClient,
        private readonly OAuthRedirectUriMatcher $redirectUriMatcher,
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
                $clients[] = self::createClient($data, $target);
            }
        }

        usort($clients, fn (OAuthClient $a, OAuthClient $b): int => [!$a->redirectUriAllowed, $a->clientId] <=> [!$b->redirectUriAllowed, $b->clientId]);

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

    public static function createClient(array $data, string $target): OAuthClient
    {
        $client = new OAuthClient();
        $client->id = $data['id'] ?? null;
        $client->clientId = (string) ($data['clientId'] ?? '');
        $client->name = !empty($data['name']) ? $data['name'] : null;
        $client->description = !empty($data['description']) ? $data['description'] : null;
        $client->redirectUris = array_values(array_filter($data['redirectUris'] ?? [], is_string(...)));
        $client->redirectUriAllowed = OAuthRedirectUriMatcher::isAllowed($target, $client->redirectUris, $data['rootUrl'] ?? null);

        return $client;
    }
}
