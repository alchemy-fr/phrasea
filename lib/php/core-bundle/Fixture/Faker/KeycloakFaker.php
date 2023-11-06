<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Fixture\Faker;

use Alchemy\AuthBundle\Client\KeycloakClient;
use Faker\Generator;
use Faker\Provider\Base as BaseProvider;

class KeycloakFaker extends BaseProvider
{
    private ?string $accessToken = null;

    public function __construct(
        Generator $generator,
        private readonly KeycloakClient $keycloakClient,
    )
    {
        parent::__construct($generator);
    }

    public function keycloakUser(
        string $username,
        array $roles = [],
        string $password = 'xxx',
    ): string {
        if (null === $this->accessToken) {
            ['access_token' => $this->accessToken] = $this->keycloakClient->getClientCredentialAccessToken();
        }

        $email = $username.'@phrasea.local';

        $response = $this->keycloakClient->createUser([
            'email' => $email,
            'username' => $username,
            'emailVerified' => true,
            'enabled' => true,
            'realmRoles' => $roles,
            'credentials' => [[
                'type' => 'password',
                'value' => $password,
                'temporary' => true,
            ]]
        ], $this->accessToken);

        return $response['id'];
    }
}
