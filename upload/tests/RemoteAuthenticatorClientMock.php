<?php

declare(strict_types=1);

namespace App\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

class RemoteAuthenticatorClientMock extends Client
{
    public function request($method, $uri = '', array $options = [])
    {
        return new Response(200, [
            'Content-Type' => 'application/json',
        ], json_encode($this->getJsonBody($uri, $options)));
    }

    private function getJsonBody(string $uri, array $options): array
    {
        $accessToken = explode(' ', $options['headers']['Authorization'], 2)[1];
        $roles = ['ROLE_USER'];
        if ($accessToken === 'admin@alchemy.fr') {
            $roles[] = 'ROLE_SUPER_ADMIN';
        }

        switch ($uri) {
            case '/me':
                return [
                    'user_id' => '123',
                    'email' => $accessToken,
                    'roles' => $roles,
                ];
        }
    }
}
