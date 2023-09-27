<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Tests\Client;

use DateTimeImmutable;
use InvalidArgumentException;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use LogicException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class KeycloakClientTestMock implements HttpClientInterface
{
    final public const USER_TOKEN = '__VALID_USER_TOKEN__';
    final public const ADMIN_TOKEN = '__VALID_ADMIN_TOKEN__';

    final public const USER_UID = '123';
    final public const ADMIN_UID = '4242';

    private const USERS = [
        self::ADMIN_UID => [
            'username' => 'admin',
            'roles' => ['admin']
        ],
        self::USER_UID => [
            'username' => 'user',
            'roles' => []
        ],
    ];

    public function __construct()
    {
    }

    public static function getJwtFor(string $userId): string
    {
        $configuration = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::file(__DIR__ . '/key.pem'),
            InMemory::file(__DIR__ . '/key.pub'),
        );

        $now = new DateTimeImmutable();
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
            ->withClaim('roles', self::USERS[$userId]['roles'] ?? [])
            ->withClaim('groups', self::USERS[$userId]['groups'] ?? [])
            ->getToken(new Sha256(), InMemory::file(__DIR__.'/key.pem'));

        return $token->toString();
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $args = [$method, $url, $options];
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

        return match (true) {
            str_ends_with($url, '/realms/phrasea') => $this->createResponse($args, 200, [
                'public_key' => file_get_contents(__DIR__.'/key.pub'),
            ]),
            str_ends_with($url, '/admin/realms/phrasea/users'),
            str_ends_with($url, '/admin/realms/phrasea/groups') => $this->createResponse($args, 200, []),
            default => throw new InvalidArgumentException(sprintf('Unsupported mock for URI "%s"', $url)),
        };
    }

    private function createResponse(array $args, int $code, array $data): ResponseInterface
    {
        $callback = function ($method, $url, $options) use ($code, $data): MockResponse {
            return new JsonMockResponse($data, [
                'http_code' => $code,
            ]);
        };

        $client = new MockHttpClient($callback);

        return $client->request(...$args);
    }

    public function stream(iterable|ResponseInterface $responses, float $timeout = null): ResponseStreamInterface
    {
        throw new LogicException('Not implemented yet');
    }

    public function withOptions(array $options): static
    {
        return $this;
    }
}
