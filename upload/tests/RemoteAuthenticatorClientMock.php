<?php

declare(strict_types=1);

namespace App\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

class RemoteAuthenticatorClientMock extends Client
{
    public function request($method, $uri = '', array $options = [])
    {
        $accessToken = explode(' ', $options['headers']['Authorization'], 2)[1];
        $roles = ['ROLE_USER'];
        if ('admin@alchemy.fr' === $accessToken) {
            $roles[] = 'ROLE_SUPER_ADMIN';
        }

        if (!(strpos($accessToken, '@') > 0)) {
            return $this->createResponse(401, [
                'error' => 'invalid_token',
            ]);
        }

        if (empty($accessToken)) {
            return $this->createResponse(401, [
                'error' => 'missing_token',
            ]);
        }

        switch ($uri) {
            case '/me':
                return $this->createResponse(200, [
                    'user_id' => '123',
                    'email' => $accessToken,
                    'roles' => $roles,
                ]);
        }
    }

    private function createResponse(int $code, array $data): Response
    {
        return new Response($code, [
            'Content-Type' => 'application/json',
        ], json_encode($data));
    }
}
