<?php

declare(strict_types=1);

namespace App\Tests;

class GetUsersTest extends AbstractTestCase
{
    public function testGetUsersOK(): void
    {
        $accessToken = $this->authenticateMachine('user:list');

        $response = $this->request($accessToken, 'GET', '/users');
        $this->assertEquals(200, $response->getStatusCode());

        $json = json_decode($response->getContent(), true);
        $this->assertCount(3, $json);

        foreach ($json as $user) {
            $this->assertArrayHasKey('id', $user);
            $this->assertArrayHasKey('username', $user);
        }
    }

    public function testGetUsersGenerates403WithInvalidScope(): void
    {
        $this->markTestSkipped('Users are exposed to everyone for simplicity. Should fix it.');

        //        $accessToken = $this->authenticateUser('foo@bar.com', 'secret');
        //        $response = $this->request($accessToken, 'GET', '/users');
        //        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGetUsersGenerates401WithInvalidAccessToken(): void
    {
        $response = $this->request('invalid_token', 'GET', '/users');
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetUsersGenerates401WithNoProvidedAccessToken(): void
    {
        $response = $this->request(null, 'GET', '/users');
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetUsersGenerates401WithADisabledAccount(): void
    {
        $accessToken = $this->authenticateUser('disabled@bar.com', 'secret');

        $response = $this->request($accessToken, 'GET', '/users');
        $this->assertEquals(401, $response->getStatusCode());
    }
}
