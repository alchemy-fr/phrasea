<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;

class AccessTokenTest extends AbstractExposeTestCase
{
    public function testAcceptsAnAccessTokenForTheApi(): void
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

    public function testRefusesATokenWithoutTheAudienceOfExpose(): void
    {
        $response = $this->request($this->createUserJwt(['aud' => null]), 'GET', '/publications');
        $this->assertSame(401, $response->getStatusCode());

        // A token for another API only
        $response = $this->request($this->createUserJwt(['azp' => 'databox-app', 'aud' => 'another-api-admin']), 'GET', '/publications');
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testAcceptsAnyClientGivenTheAudienceOfExpose(): void
    {
        // e.g. a client created by Databox for its integrations, which has the "expose-api" client scope
        $response = $this->request($this->createUserJwt([
            'azp' => 'an-integration',
            'aud' => [KeycloakClientTestMock::getApiAudience(), 'account'],
        ]), 'GET', '/publications');
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testAcceptsTheAdminClientOfExposeWithoutAudience(): void
    {
        $response = $this->request($this->createUserJwt([
            'azp' => KeycloakClientTestMock::getApiAudience(),
            'aud' => null,
        ]), 'GET', '/publications');
        $this->assertSame(200, $response->getStatusCode());
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
