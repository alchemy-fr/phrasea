<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\RemoteAuthBundle\Security\RemoteAuthenticatorClientTestMock;

class CommitTest extends ApiTestCase
{
    public function testGetCommitsOK(): void
    {
        $response = $this->request(
            RemoteAuthenticatorClientTestMock::ADMIN_TOKEN,
            'GET',
            '/commits'
        );
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertTrue(is_array($json), 'Not an array');
        $this->assertTrue(empty($json), 'Not empty');
    }

    public function testGetCommitsWithAnonymousUser(): void
    {
        $response = $this->request(null, 'GET', '/commits');
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetCommitsWithInvalidToken(): void
    {
        $response = $this->request('invalid_token', 'GET', '/commits');
        $this->assertEquals(401, $response->getStatusCode());
    }
}
