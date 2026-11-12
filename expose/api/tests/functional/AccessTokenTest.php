<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;

class AccessTokenTest extends AbstractExposeTestCase
{
    public function testAcceptsTheAccessTokenOfAnAllowedClient(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/publications');
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRefusesAnIdToken(): void
    {
        $response = $this->request($this->createUserJwt(['typ' => 'ID']), 'GET', '/publications');
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Invalid token.', json_decode((string) $response->getContent(), true)['message']);
    }

    public function testRefusesAnUnknownClient(): void
    {
        $response = $this->request($this->createUserJwt(['azp' => 'unknown-client']), 'GET', '/publications');
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testAcceptsAClientCreatedByDatabox(): void
    {
        // "an-integration" carries the attribute set by Databox in the Keycloak mock
        $response = $this->request($this->createUserJwt(['azp' => 'an-integration']), 'GET', '/publications');
        $this->assertSame(200, $response->getStatusCode());

        // a public client of the realm not created by Databox
        $response = $this->request($this->createUserJwt(['azp' => 'account-console']), 'GET', '/publications');
        $this->assertSame(401, $response->getStatusCode());
    }

    private function createUserJwt(array $claims): string
    {
        return KeycloakClientTestMock::createJwt([
            'preferred_username' => 'user',
            'roles' => ['expose'],
            ...$claims,
        ], KeycloakClientTestMock::USER_UID);
    }
}
