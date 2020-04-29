<?php

declare(strict_types=1);

namespace App\Tests;


use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;

class BulkDataTest extends AbstractTestCase
{
    public function testBulkDataEditOK(): void
    {
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'GET', '/bulk-data');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{}', $response->getContent());

        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'POST', '/bulk-data/edit', [
            'data' => [
                'foo' => 'bar',
            ],
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(true, $json);

        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'GET', '/bulk-data');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"foo":"bar"}', $response->getContent());
    }

    public function testBulkDataEditWithANonAdminUser(): void
    {
        $response = $this->request(AuthServiceClientTestMock::USER_TOKEN, 'POST', '/bulk-data/edit', [
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
