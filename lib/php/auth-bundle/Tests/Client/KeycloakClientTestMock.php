<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Tests\Client;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class KeycloakClientTestMock implements HttpClientInterface
{
    final public const string USER_TOKEN = '__VALID_USER_TOKEN__';
    final public const string ADMIN_TOKEN = '__VALID_ADMIN_TOKEN__';

    final public const string USER_UID = '12345678-fc1a-492b-9e76-c2e8e6979786';
    final public const string OTHER_USER_UID = '99999999-fc1a-492b-9e76-c2e8e6979786';
    final public const string ADMIN_UID = '42424242-fc1a-492b-9e76-c2e8e6979786';
    /**
     * A user disabled in the IdP: it cannot log in nor be impersonated.
     */
    final public const string DISABLED_USER_UID = '00000000-fc1a-492b-9e76-c2e8e6979786';

    final public const array USERS = [
        self::ADMIN_UID => [
            'username' => 'admin',
            'roles' => ['admin', 'databox', 'expose', 'uploader'],
            'resource_access' => [
                'realm-management' => ['roles' => ['impersonation', 'manage-clients', 'view-clients']],
            ],
        ],
        self::OTHER_USER_UID => [
            'username' => 'other_user',
            'roles' => ['databox', 'expose', 'uploader'],
        ],
        self::USER_UID => [
            'username' => 'user',
            'roles' => ['databox', 'expose', 'uploader'],
        ],
    ];

    final public const string GROUP_UID = '77777777-fc1a-492b-9e76-c2e8e6979786';
    /**
     * Name of GROUP_UID: a top level group of this name already exists.
     */
    final public const string GROUP_NAME = 'group';

    /**
     * Groups created during the test, by ID, as sent to the IdP (renames included).
     *
     * @var array<string, array<string, mixed>>
     */
    public static array $createdGroups = [];

    /**
     * Write requests sent to the groups and memberships of the admin API during the test:
     * method, path, token of the Authorization header and JSON body.
     *
     * @var list<array{method: string, path: string, token: ?string, json: mixed}>
     */
    public static array $groupWrites = [];

    public static function resetGroups(): void
    {
        self::$createdGroups = [];
        self::$groupWrites = [];
    }

    /**
     * Client ID refused by the IdP on creation, as already taken.
     */
    final public const string EXISTING_CLIENT_ID = 'expose-integration';

    /**
     * OAuth clients of the realm, as returned by the IdP.
     */
    final public const array CLIENTS = [
        [
            'id' => 'c0000001-fc1a-492b-9e76-c2e8e6979786',
            'clientId' => 'expose-integration',
            'name' => 'Expose integration',
            'enabled' => true,
            'publicClient' => true,
            'bearerOnly' => false,
            'standardFlowEnabled' => true,
            'rootUrl' => 'http://localhost',
            'redirectUris' => ['http://localhost/*'],
        ],
        [
            'id' => 'c0000002-fc1a-492b-9e76-c2e8e6979786',
            'clientId' => 'databox-app',
            'name' => 'Databox',
            'enabled' => true,
            'publicClient' => true,
            'bearerOnly' => false,
            'standardFlowEnabled' => true,
            'rootUrl' => 'https://databox.phrasea.test',
            'redirectUris' => ['/*'],
        ],
        [
            'id' => 'c0000003-fc1a-492b-9e76-c2e8e6979786',
            'clientId' => 'databox-admin',
            'enabled' => true,
            'publicClient' => false,
            'bearerOnly' => false,
            'standardFlowEnabled' => true,
            'redirectUris' => ['http://localhost/*'],
        ],
        [
            'id' => 'c0000004-fc1a-492b-9e76-c2e8e6979786',
            'clientId' => 'account-console',
            'name' => '${client_account-console}',
            'enabled' => true,
            'publicClient' => true,
            'bearerOnly' => false,
            'standardFlowEnabled' => true,
            'rootUrl' => '${authBaseUrl}',
            'redirectUris' => ['/realms/phrasea/account/*'],
        ],
        [
            'id' => 'c0000005-fc1a-492b-9e76-c2e8e6979786',
            'clientId' => 'an-integration',
            'enabled' => true,
            'publicClient' => true,
            'bearerOnly' => false,
            'standardFlowEnabled' => true,
            'redirectUris' => ['http://localhost/integrations/11111111-1111-1111-1111-111111111111/code'],
        ],
    ];

    /**
     * Clients created during the test, by client ID, as sent to the IdP.
     *
     * @var array<string, array<string, mixed>>
     */
    public static array $createdClients = [];

    /**
     * Members of the groups, as returned by the IdP.
     */
    final public const array GROUP_MEMBERS = [
        self::GROUP_UID => [
            ['id' => self::USER_UID, 'username' => 'user', 'enabled' => true],
            ['id' => self::OTHER_USER_UID, 'username' => 'other_user', 'enabled' => true],
            ['id' => self::DISABLED_USER_UID, 'username' => 'disabled_user', 'enabled' => false],
        ],
    ];

    /**
     * @param string[]      $extraRoles     realm roles granted on top of the user's ones (e.g. an application role)
     * @param string[]|null $groups         the user's groups, instead of its ones in USERS
     * @param array|null    $resourceAccess the client roles ("resource_access" claim), instead of its ones in USERS
     */
    public static function getJwtFor(string $userId, array $extraRoles = [], ?array $groups = null, ?array $resourceAccess = null): string
    {
        $configuration = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::file(__DIR__.'/key.pem'),
            InMemory::file(__DIR__.'/key.pub'),
        );

        $now = new \DateTimeImmutable();
        $token = $configuration
            ->builder()
            ->issuedBy(getenv('KEYCLOAK_URL').'/realms/phrasea')
            // Configures the time that the token was issue (iat claim)
            ->issuedAt($now)
            // Configures the time that the token can be used (nbf claim)
            ->canOnlyBeUsedAfter($now->modify('+1 minute'))
            ->expiresAt($now->modify('+1 hour'))
            ->relatedTo($userId)
            ->withClaim('azp', 'test')
            ->withClaim('preferred_username', self::USERS[$userId]['username'])
            ->withClaim('roles', array_values(array_unique([...(self::USERS[$userId]['roles'] ?? []), ...$extraRoles])))
            ->withClaim('groups', $groups ?? self::USERS[$userId]['groups'] ?? [])
            ->withClaim('resource_access', $resourceAccess ?? self::USERS[$userId]['resource_access'] ?? [])
            ->getToken(new Sha256(), InMemory::file(__DIR__.'/key.pem'));

        return $token->toString();
    }

    public static function getClientCredentialJwt(string $scope = ''): string
    {
        $configuration = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::file(__DIR__.'/key.pem'),
            InMemory::file(__DIR__.'/key.pub'),
        );

        $now = new \DateTimeImmutable();
        $token = $configuration
            ->builder()
            ->issuedBy(getenv('KEYCLOAK_URL').'/realms/phrasea')
            // Configures the time that the token was issue (iat claim)
            ->issuedAt($now)
            // Configures the time that the token can be used (nbf claim)
            ->canOnlyBeUsedAfter($now->modify('+1 minute'))
            ->withClaim('client_id', 'machine_client')
            ->expiresAt($now->modify('+1 hour'))
            ->withClaim('azp', 'test')
            ->withClaim('roles', [])
            ->withClaim('scope', $scope)
            ->getToken(new Sha256(), InMemory::file(__DIR__.'/key.pem'));

        return $token->toString();
    }

    /**
     * Query of the last request sent to each path, for the tests to check what reached the IdP.
     *
     * @var array<string, array<string, mixed>>
     */
    public static array $lastQueries = [];

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $args = [$method, $url, $options];
        self::$lastQueries[(string) parse_url($url, PHP_URL_PATH)] = $options['query'] ?? [];
        if ('POST' === $method && str_ends_with($url, '/token')) {
            if ('client_credentials' === $options['body']['grant_type']) {
                return $this->createResponse($args, 200, [
                    'access_token' => self::ADMIN_TOKEN,
                    'expires_in' => time() + 3600,
                ]);
            }
            $this->createResponse($args, 401, [
                'error' => 'invalid_grant_type_for_test',
            ]);
        }

        if (str_ends_with($url, '/admin/realms/phrasea/clients')) {
            return $this->handleClients($args);
        }

        if ('GET' !== $method && 1 === preg_match('#/admin/realms/phrasea/(?:groups(?:/[^/]+)?|users/[^/]+/groups/[^/]+)$#', $url)) {
            return $this->handleGroupWrite($args);
        }

        if (1 === preg_match('#/admin/realms/phrasea/users/([^/]+)/role-mappings/realm/composite$#', $url, $match)) {
            return $this->createResponse($args, 200, array_map(fn (string $role): array => [
                'name' => $role,
            ], self::USERS[$match[1]]['roles'] ?? []));
        }

        if (1 === preg_match('#/admin/realms/phrasea/users/([^/]+)/groups$#', $url, $match)) {
            return $this->createResponse($args, 200, array_map(fn (string $group): array => [
                'id' => $group,
            ], self::USERS[$match[1]]['groups'] ?? []));
        }

        if (1 === preg_match('#/admin/realms/phrasea/groups/([^/]+)/members$#', $url, $match)) {
            return $this->createResponse($args, 200, array_slice(
                self::GROUP_MEMBERS[$match[1]] ?? [],
                (int) ($options['query']['first'] ?? 0),
                (int) ($options['query']['max'] ?? 100),
            ));
        }

        if (1 === preg_match('#/admin/realms/phrasea/groups/([^/]+)$#', $url, $match)) {
            if (null === $name = self::getGroupName($match[1])) {
                return $this->createResponse($args, 404, [
                    'error' => 'Could not find group by id',
                ]);
            }

            return $this->createResponse($args, 200, [
                'id' => $match[1],
                'name' => $name,
                'path' => '/'.$name,
            ]);
        }

        if (1 === preg_match('#/admin/realms/phrasea/users/([^/]+)$#', $url, $match) && self::DISABLED_USER_UID === $match[1]) {
            return $this->createResponse($args, 200, [
                'id' => $match[1],
                'username' => 'disabled_user',
                'enabled' => false,
            ]);
        }

        if (1 === preg_match('#/admin/realms/phrasea/users/(\d+)$#', $url, $match)) {
            return $this->createResponse($args, 200, array_merge(self::USERS[$match[1]], [
                'id' => $match[1],
            ]));
        }

        return match (true) {
            str_ends_with($url, '/realms/phrasea') => $this->createResponse($args, 200, [
                'public_key' => file_get_contents(__DIR__.'/key.pub'),
            ]),
            str_ends_with($url, '/admin/realms/phrasea/users'),
            str_ends_with($url, '/admin/realms/phrasea/groups') => $this->createResponse($args, 200, []),
            str_contains($url, '/admin/realms/phrasea/users/') => $this->createResponse($args, 200, [
                'id' => '123',
                'username' => 'user',
                'firstName' => 'Test',
                'lastName' => 'User',
                'email' => 'user@phrasea.test',
            ]),
            default => throw new \InvalidArgumentException(sprintf('Unsupported mock for URI "%s"', $url)),
        };
    }

    private function handleClients(array $args): ResponseInterface
    {
        [$method, , $options] = $args;

        if ('POST' === $method) {
            $data = $options['json'] ?? [];
            $clientId = (string) ($data['clientId'] ?? '');
            if (self::EXISTING_CLIENT_ID === $clientId || isset(self::$createdClients[$clientId])) {
                return $this->createResponse($args, 409, [
                    'errorMessage' => sprintf('Client %s already exists', $clientId),
                ]);
            }
            self::$createdClients[$clientId] = $data;

            return $this->createResponse($args, 201, []);
        }

        $query = $options['query'] ?? [];
        $clients = [...self::CLIENTS];
        foreach (self::$createdClients as $clientId => $data) {
            $clients[] = [
                ...$data,
                'id' => md5($clientId),
            ];
        }

        if (isset($query['clientId'])) {
            $search = 'true' === (string) ($query['search'] ?? 'false');
            $clients = array_filter($clients, fn (array $client): bool => $search
                ? false !== stripos($client['clientId'], (string) $query['clientId'])
                : $client['clientId'] === $query['clientId']);
        }

        return $this->createResponse($args, 200, array_slice(
            array_values($clients),
            (int) ($query['first'] ?? 0),
            (int) ($query['max'] ?? 100),
        ));
    }

    /**
     * Name of a group known by the IdP, null for an unknown group.
     */
    private static function getGroupName(string $groupId): ?string
    {
        if (isset(self::$createdGroups[$groupId])) {
            return (string) self::$createdGroups[$groupId]['name'];
        }

        return isset(self::GROUP_MEMBERS[$groupId]) ? self::GROUP_NAME : null;
    }

    private static function isGroupNameTaken(string $name, ?string $exceptId = null): bool
    {
        foreach ([...array_keys(self::GROUP_MEMBERS), ...array_keys(self::$createdGroups)] as $groupId) {
            if ($groupId !== $exceptId && self::getGroupName((string) $groupId) === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Group creation, rename and deletion, membership changes: what Keycloak answers to an
     * allowed token (204, 201 with the Location of the created group, 404, 409 on a taken name).
     */
    private function handleGroupWrite(array $args): ResponseInterface
    {
        [$method, $url, $options] = $args;
        $path = (string) parse_url($url, PHP_URL_PATH);
        $json = $options['json'] ?? null;
        $authorization = $options['headers']['Authorization'] ?? null;
        self::$groupWrites[] = [
            'method' => $method,
            'path' => $path,
            'token' => is_string($authorization) ? preg_replace('#^Bearer #', '', $authorization) : null,
            'json' => $json,
        ];
        $notFound = fn (): ResponseInterface => $this->createResponse($args, 404, [
            'error' => 'Could not find group by id',
        ]);
        $conflict = fn (string $name): ResponseInterface => $this->createResponse($args, 409, [
            'errorMessage' => sprintf('Top level group named \'%s\' already exists.', $name),
        ]);

        if (1 === preg_match('#/users/[^/]+/groups/([^/]+)$#', $path, $match)) {
            return null !== self::getGroupName($match[1]) ? $this->createResponse($args, 204, []) : $notFound();
        }

        if (str_ends_with($path, '/groups')) {
            $name = (string) ($json['name'] ?? '');
            if (self::isGroupNameTaken($name)) {
                return $conflict($name);
            }
            $hash = md5($name);
            $groupId = sprintf('%s-%s-%s-%s-%s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 12, 4), substr($hash, 16, 4), substr($hash, 20, 12));
            self::$createdGroups[$groupId] = $json;

            return $this->createResponse($args, 201, [], [
                'Location' => $url.'/'.$groupId,
            ]);
        }

        preg_match('#/groups/([^/]+)$#', $path, $match);
        $groupId = $match[1];
        if (null === $currentName = self::getGroupName($groupId)) {
            return $notFound();
        }

        if ('DELETE' === $method) {
            unset(self::$createdGroups[$groupId]);

            return $this->createResponse($args, 204, []);
        }

        $name = $json['name'] ?? $currentName;
        if (self::isGroupNameTaken($name, $groupId)) {
            return $conflict($name);
        }
        self::$createdGroups[$groupId] = [
            ...self::$createdGroups[$groupId] ?? [],
            ...$json,
            'name' => $name,
        ];

        return $this->createResponse($args, 204, []);
    }

    private function createResponse(array $args, int $code, array $data, array $headers = []): ResponseInterface
    {
        $callback = function ($method, $url, $options) use ($code, $data, $headers): MockResponse {
            return new JsonMockResponse($data, [
                'http_code' => $code,
                'response_headers' => $headers,
            ]);
        };

        $client = new MockHttpClient($callback);

        return $client->request(...$args);
    }

    public function stream(iterable|ResponseInterface $responses, ?float $timeout = null): ResponseStreamInterface
    {
        throw new \LogicException('Not implemented yet');
    }

    public function withOptions(array $options): static
    {
        return $this;
    }
}
