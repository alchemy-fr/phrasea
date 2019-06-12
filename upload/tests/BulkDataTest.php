<?php

declare(strict_types=1);

namespace App\Tests;

use App\Model\User;

class BulkDataTest extends ApiTestCase
{
    public function testBulkDataEditOK(): void
    {
        $response = $this->request(User::ADMIN_USER, 'GET', '/bulk-data');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{}', $response->getContent());

        $response = $this->request(User::ADMIN_USER, 'POST', '/bulk-data/edit', [
            'data' => [
                'foo' => 'bar',
            ],
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(true, $json);

        $response = $this->request(User::ADMIN_USER, 'GET', '/bulk-data');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"foo":"bar"}', $response->getContent());
    }

    public function testBulkDataEditWithANonAdminUser(): void
    {
        $response = $this->request('foo@bar.com', 'POST', '/bulk-data/edit', [
            'data' => [
                'foo' => 'bar',
            ],
        ]);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testBulkDataEditWithAnonymousUser(): void
    {
        $response = $this->request(null, 'POST', '/bulk-data/edit', [
            'data' => [
                'foo' => 'bar',
            ],
        ]);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testBulkDataGetWithAnonymousUser(): void
    {
        $response = $this->request(null, 'GET', '/bulk-data');
        $this->assertEquals(401, $response->getStatusCode());
    }
}
