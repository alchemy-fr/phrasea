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

        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('user_id', $json);
        $this->assertArrayHasKey('username', $json);
        $this->assertArrayHasKey('email', $json);
        $this->assertEquals('foo@bar.com', $json['username']);
        $this->assertEquals('foo@bar.com', $json['email']);
        $this->assertArrayHasKey('groups', $json);
        $this->assertIsArray($json['groups']);
        $this->assertCount(2, $json['groups']);
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
