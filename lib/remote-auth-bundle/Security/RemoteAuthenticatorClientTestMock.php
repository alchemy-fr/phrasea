<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Security;

use Alchemy\RemoteAuthBundle\Security\Token\RemoteAuthToken;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

class RemoteAuthenticatorClientTestMock extends Client
{
    const USER_TOKEN = RemoteAuthToken::TOKEN_PREFIX.'__VALID_USER_TOKEN__';
    const ADMIN_TOKEN = RemoteAuthToken::TOKEN_PREFIX.'__VALID_ADMIN_TOKEN__';

    public function request($method, $uri = '', array $options = [])
    {
        $accessToken = explode(' ', $options['headers']['Authorization'], 2)[1];
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

        $roles = ['ROLE_USER'];
        if (self::ADMIN_TOKEN === $accessToken) {
            $roles[] = 'ROLE_SUPER_ADMIN';
        }

        switch ($uri) {
            case '/me':
                return $this->createResponse(200, [
                    'user_id' => '123',
                    'username' => $accessToken,
                    'roles' => $roles,
                ]);
            case '/token-info':
                return $this->createResponse(200, [
                    'scopes' => [],
                    'user' => [
                        'id' => '123',
                        'username' => $accessToken,
                        'roles' => $roles,
                    ],
                ]);
        }

        return $this->createResponse(404, [
            'error' => 'not_found',
        ]);
    }

    private function createResponse(int $code, array $data): Response
    {
        return new Response($code, [
            'Content-Type' => 'application/json',
        ], json_encode($data));
    }
}
