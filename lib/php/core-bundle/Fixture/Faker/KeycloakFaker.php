<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Fixture\Faker;

use Alchemy\AuthBundle\Client\OAuthClient;
use Faker\Generator;
use Faker\Provider\Base as BaseProvider;

class KeycloakFaker extends BaseProvider
{
    private ?string $accessToken = null;

    public function __construct(
        Generator $generator,
        private readonly OAuthClient $authClient,
    )
    {
        parent::__construct($generator);
    }

    public function keycloakUser(
        string $username,
        array $roles = [],
    ): string {
        if (null === $this->accessToken) {
            ['access_token' => $this->accessToken] = $this->authClient->getClientCredentialAccessToken();
        }

        $email = $username.'@phrasea.local';

        $response = $this->authClient->createUser([
            'email' => $email,
            'username' => $username,
            'emailVerified' => true,
            'enabled' => true,
            'realmRoles' => $roles,
        ], $this->accessToken);

        return $response['id'];
    }
}
