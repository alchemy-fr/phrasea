<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;

class TargetFormSchemaTest extends AbstractUploaderTestCase
{
    public function testTargetWithoutSchemaReturnsNull(): void
    {
        $target = $this->getOrCreateDefaultTarget();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/targets/'.$target->getId().'/form-schema');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull(json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testTargetSchema(): void
    {
        $target = $this->getOrCreateDefaultTarget();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/form-schemas', [
            'target' => '/targets/'.$target->getId(),
            'data' => ['foo' => 'bar'],
        ]);
        $this->assertEquals(201, $response->getStatusCode());
        $schemaId = $response->toArray()['id'];

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/targets/'.$target->getId().'/form-schema');
        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->toArray();
        $this->assertEquals($schemaId, $data['id']);
        $this->assertEquals(['foo' => 'bar'], $data['data']);
    }

    public function testUnknownTargetReturns404(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/targets/00000000-0000-0000-0000-000000000000/form-schema');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAnonymousReturns401(): void
    {
        $target = $this->getOrCreateDefaultTarget();

        $response = $this->request(null, 'GET', '/targets/'.$target->getId().'/form-schema');
        $this->assertEquals(401, $response->getStatusCode());
    }
}
