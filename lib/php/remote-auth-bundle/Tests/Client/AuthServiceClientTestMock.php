<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Tests\Client;

use Alchemy\RemoteAuthBundle\Security\Token\RemoteAuthToken;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

class AuthServiceClientTestMock extends Client
{
    final public const USER_TOKEN = '__VALID_USER_TOKEN__';
    final public const ADMIN_TOKEN = '__VALID_ADMIN_TOKEN__';

    final public const USER_UID = '123';
    final public const ADMIN_UID = '4242';

    final public const USERS_ID = [
        self::USER_TOKEN => self::USER_UID,
        self::ADMIN_TOKEN => self::ADMIN_UID,
    ];

    public function request(string $method, $uri = '', array $options = []): ResponseInterface
    {
        if ('token' === $uri) {
            if ('client_credentials' === $options[RequestOptions::FORM_PARAMS]['grant_type']) {
                return $this->createResponse(200, [
                    'access_token' => self::ADMIN_TOKEN,
                    'expires_in' => time() + 3600,
                ]);
            }
            $this->createResponse(401, [
                'error' => 'invalid_grant_type_for_test',
            ]);
        }

        $accessToken = isset($options[RequestOptions::HEADERS]['Authorization'])
            ? explode(' ', (string) $options[RequestOptions::HEADERS]['Authorization'], 2)[1]
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

        return match ($uri) {
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
            default => throw new \InvalidArgumentException(sprintf('Unsupported mock for URI "%s"', $uri)),
        };
    }

    private function createResponse(int $code, array $data): Response
    {
        return new Response($code, [
            'Content-Type' => 'application/json',
        ], json_encode($data, JSON_THROW_ON_ERROR));
    }
}
