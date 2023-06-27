<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Tests\Client;

use Alchemy\RemoteAuthBundle\Security\Token\RemoteAuthToken;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

class AuthServiceClientTestMock extends Client
{
    public const USER_TOKEN = RemoteAuthToken::TOKEN_PREFIX.'__VALID_USER_TOKEN__';
    public const ADMIN_TOKEN = RemoteAuthToken::TOKEN_PREFIX.'__VALID_ADMIN_TOKEN__';

    public const USER_UID = '123';
    public const ADMIN_UID = '4242';

    public const USERS_ID = [
        self::USER_TOKEN => self::USER_UID,
        self::ADMIN_TOKEN => self::ADMIN_UID,
    ];

    public function request(string $method, $uri = '', array $options = []): ResponseInterface
    {
        if ('oauth/v2/token' === $uri) {
            if ('client_credentials' === $options['json']['grant_type']) {
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
            ? explode(' ', $options['headers']['Authorization'], 2)[1]
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

        switch ($uri) {
            case '/me':
                return $this->createResponse(200, [
                    'user_id' => $userId,
                    'username' => $accessToken,
                    'roles' => $roles,
                    'groups' => [],
                ]);
            case '/token-info':
                return $this->createResponse(200, [
                    'scopes' => [],
                    'user' => [
                        'id' => $userId,
                        'username' => $accessToken,
                        'roles' => $roles,
                        'groups' => [],
                    ],
                ]);
            case '/users':
            case '/groups':
                return $this->createResponse(200, [
                ]);
            default:
                throw new \InvalidArgumentException(sprintf('Unsupported mock for URI "%s"', $uri));
        }
    }

    private function createResponse(int $code, array $data): Response
    {
        return new Response($code, [
            'Content-Type' => 'application/json',
        ], json_encode($data));
    }
}
