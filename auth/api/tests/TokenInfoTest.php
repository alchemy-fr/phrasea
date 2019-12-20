<?php

declare(strict_types=1);

namespace App\Tests;

class TokenInfoTest extends AbstractTestCase
{
    public function testTokenInfoOK(): void
    {
        $accessToken = $this->authenticateUser('foo@bar.com', 'secret');

        $response = $this->request($accessToken, 'GET', '/token-info');
        $this->assertEquals(200, $response->getStatusCode());

        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('scopes', $json);
        $this->assertEquals([], $json['scopes']);
        $this->assertArrayHasKey('user', $json);
        $this->assertEquals('foo@bar.com', $json['user']['email']);
        $this->assertEquals('foo@bar.com', $json['user']['username']);
    }

    public function testTokenInfoGenerates401WithInvalidAccessToken(): void
    {
        $response = $this->request('invalid_token', 'GET', '/token-info');
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testTokenInfoGenerates401WithNoProvidedAccessToken(): void
    {
        $response = $this->request(null, 'GET', '/token-info');
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testTokenInfoGenerates401WithADisabledAccount(): void
    {
        $accessToken = $this->authenticateUser('disabled@bar.com', 'secret');

        $response = $this->request($accessToken, 'GET', '/token-info');
        $this->assertEquals(401, $response->getStatusCode());
    }
}
