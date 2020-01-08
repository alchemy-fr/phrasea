<?php

declare(strict_types=1);

namespace App\Tests;

class MeTest extends AbstractTestCase
{
    public function testMeOK(): void
    {
        $accessToken = $this->authenticateUser('foo@bar.com', 'secret');

        $response = $this->request($accessToken, 'GET', '/me');
        $this->assertEquals(200, $response->getStatusCode());

        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('user_id', $json);
        $this->assertEquals('foo@bar.com', $json['username']);
        $this->assertEquals('foo@bar.com', $json['email']);
    }

    public function testMeGenerates401WithInvalidAccessToken(): void
    {
        $response = $this->request('invalid_token', 'GET', '/me');
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testMeGenerates401WithNoProvidedAccessToken(): void
    {
        $response = $this->request(null, 'GET', '/me');
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testMeGenerates401WithADisabledAccount(): void
    {
        $accessToken = $this->authenticateUser('disabled@bar.com', 'secret');

        $response = $this->request($accessToken, 'GET', '/me');
        $this->assertEquals(401, $response->getStatusCode());
    }
}
