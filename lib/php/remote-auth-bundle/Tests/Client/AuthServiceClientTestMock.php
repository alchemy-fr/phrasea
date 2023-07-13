<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Tests\Client;

use Symfony\Component\HttpClient\Response\JsonMockResponse;
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
        if ('token' === $url) {
            if ('client_credentials' === $options['body']['grant_type']) {
                return $this->createResponse(200, [
                    'access_token' => self::ADMIN_TOKEN,
                    'expires_in' => time() + 3600,
                ]);
            }
            $this->createResponse(401, [
                'error' => 'invalid_grant_type_for_test',
            ]);
        }

        $accessToken = isset($options['headers']['Authorization'])
            ? explode(' ', (string) $options['headers']['Authorization'], 2)[1]
            : null;
        if (empty($accessToken)) {
            return $this->createResponse(401, [
                'error' => 'missing_token',
            ]);
        }

        if (!in_array($accessToken, [
            self::USER_TOKEN,
            self::ADMIN_TOKEN,
        ])) {
            return $this->createResponse(401, [
                'error' => 'invalid_token',
            ]);
        }

        $userId = self::USERS_ID[$accessToken];

        $roles = ['ROLE_USER'];
        if (self::ADMIN_TOKEN === $accessToken) {
            $roles[] = 'ROLE_SUPER_ADMIN';
        }

        return match ($url) {
            'userinfo' => $this->createResponse(200, [
                'scopes' => [],
                'user' => [
                    'id' => $userId,
                    'username' => $accessToken,
                    'roles' => $roles,
                    'groups' => [],
                ],
            ]),
            '/admin/realms/master/users',
            '/admin/realms/master/groups' => $this->createResponse(200, [
            ]),
            default => throw new \InvalidArgumentException(sprintf('Unsupported mock for URI "%s"', $url)),
        };
    }

    private function createResponse(int $code, array $data): ResponseInterface
    {
        return new JsonMockResponse($data, [
            'code' => $code,
        ]);
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
