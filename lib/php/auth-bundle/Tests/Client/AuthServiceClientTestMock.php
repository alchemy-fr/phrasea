<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Tests\Client;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class AuthServiceClientTestMock implements HttpClientInterface
{
    final public const USER_TOKEN = '__VALID_USER_TOKEN__';
    final public const ADMIN_TOKEN = '__VALID_ADMIN_TOKEN__';

    final public const USER_UID = '123';
    final public const ADMIN_UID = '4242';

    final public const USERS_ID = [
        self::USER_TOKEN => self::USER_UID,
        self::ADMIN_TOKEN => self::ADMIN_UID,
    ];

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $args = [$method, $url, $options];
        if (str_ends_with($url, '/token')) {
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

        $accessToken = isset($options['headers']['Authorization'])
            ? explode(' ', (string) $options['headers']['Authorization'], 2)[1]
            : null;
        if (empty($accessToken)) {
            return $this->createResponse($args, 401, [
                'error' => 'missing_token',
            ]);
        }

        if (!in_array($accessToken, [
            self::USER_TOKEN,
            self::ADMIN_TOKEN,
        ])) {
            return $this->createResponse($args, 401, [
                'error' => 'invalid_token',
            ]);
        }

        $userId = self::USERS_ID[$accessToken];

        $roles = [];
        if (self::ADMIN_TOKEN === $accessToken) {
            $roles[] = 'admin';
        }

        return match (true) {
            str_ends_with($url, '/userinfo') => $this->createResponse($args, 200, [
                'scopes' => [],
                'sub' => $userId,
                'preferred_username' => $accessToken,
                'roles' => $roles,
                'groups' => [],
            ]),
            str_ends_with($url, '/admin/realms/master/users'),
            str_ends_with($url, '/admin/realms/master/groups') => $this->createResponse($args, 200, []),
            default => throw new \InvalidArgumentException(sprintf('Unsupported mock for URI "%s"', $url)),
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
        throw new \LogicException('Not implemented yet');
    }

    public function withOptions(array $options): static
    {
        return $this;
    }
}
