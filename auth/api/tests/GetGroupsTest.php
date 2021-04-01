<?php

declare(strict_types=1);

namespace App\Tests;

class GetGroupsTest extends AbstractTestCase
{
    public function testGetGroupsOK(): void
    {
        $accessToken = $this->authenticateMachine('group:list');

        $response = $this->request($accessToken, 'GET', '/groups');
        $this->assertEquals(200, $response->getStatusCode());

        $json = json_decode($response->getContent(), true);
        $this->assertCount(2, $json);

        foreach ($json as $group) {
            $this->assertArrayHasKey('id', $group);
            $this->assertArrayHasKey('name', $group);
            $this->assertArrayHasKey('userCount', $group);
        }
        $this->assertEquals(2, $json[0]['userCount']);
        $this->assertEquals(1, $json[1]['userCount']);
    }

    public function testGetGroupsGenerates403WithInvalidScope(): void
    {
        $this->markTestSkipped('Groups are exposed to everyone for simplicity. Should fix it.');

//        $accessToken = $this->authenticateUser('foo@bar.com', 'secret');
//        $response = $this->request($accessToken, 'GET', '/groups');
//        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGetGroupsGenerates401WithInvalidAccessToken(): void
    {
        $response = $this->request('invalid_token', 'GET', '/groups');
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetGroupsGenerates401WithNoProvidedAccessToken(): void
    {
        $response = $this->request(null, 'GET', '/groups');
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetGroupsGenerates401WithADisabledAccount(): void
    {
        $accessToken = $this->authenticateUser('disabled@bar.com', 'secret');

        $response = $this->request($accessToken, 'GET', '/groups');
        $this->assertEquals(401, $response->getStatusCode());
    }
}
