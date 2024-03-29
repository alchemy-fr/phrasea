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
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals([], $json);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/target-params', [
            'data' => [],
            'target' => '/targets/'.$this->getOrCreateDefaultTarget()->getId(),
        ]);
        $this->assertEquals(201, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('data', $json);
        $this->assertEquals([], $json['data']);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'PUT', '/target-params/'.$json['id'], [
            'data' => [
                'foo' => 'bar',
            ],
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('data', $json);
        $this->assertEquals([
            'foo' => 'bar',
        ], $json['data']);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/target-params/'.$json['id']);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('data', $json);
        $this->assertEquals([
            'foo' => 'bar',
        ], $json['data']);
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
