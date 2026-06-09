<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;

class TargetParamsTest extends AbstractUploaderTestCase
{
    public function testTargetParamsEditOK(): void
    {
        $response = $this->request(
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/target-params');
        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->toArray();
        $this->assertEquals([], $data['hydra:member']);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/target-params', [
            'data' => [],
            'target' => '/targets/'.$this->getOrCreateDefaultTarget()->getId(),
        ]);
        $this->assertEquals(201, $response->getStatusCode());
        $data = $response->toArray();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals([], $data['data']);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'PUT', '/target-params/'.$data['id'], [
            'data' => [
                'foo' => 'bar',
            ],
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->toArray();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals([
            'foo' => 'bar',
        ], $data['data']);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/target-params/'.$data['id']);
        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->toArray();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals([
            'foo' => 'bar',
        ], $data['data']);
    }

    public function testTargetParamsEditWithANonAdminUser(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'POST', '/target-params', [
            'target' => '/targets/'.$this->getOrCreateDefaultTarget()->getId(),
            'data' => [
                'foo' => 'bar',
            ],
        ]);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testTargetParamsEditWithAnonymousUser(): void
    {
        $response = $this->request(null, 'POST', '/target-params', [
            'data' => [
                'foo' => 'bar',
            ],
        ]);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testTargetParamsGetWithAnonymousUser(): void
    {
        $response = $this->request(null, 'GET', '/target-params');
        $this->assertEquals(401, $response->getStatusCode());
    }
}
