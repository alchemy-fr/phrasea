<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;

class TargetWriteTest extends AbstractUploaderTestCase
{
    public function testAdminCreatesAndUpdatesATarget(): void
    {
        $token = KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID);

        $response = $this->request($token, 'POST', '/targets', [
            'slug' => 'my-target',
            'name' => 'My target',
            'description' => 'Where my files go',
            'targetUrl' => null,
        ]);
        $this->assertEquals(201, $response->getStatusCode());
        $data = $response->toArray();
        $this->assertSame('my-target', $data['slug']);
        $this->assertSame('My target', $data['name']);
        $this->assertSame('Where my files go', $data['description']);

        $response = $this->request($token, 'PATCH', '/targets/'.$data['id'], [
            'name' => 'Renamed',
            'description' => null,
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->toArray();
        $this->assertSame('Renamed', $data['name']);
        $this->assertNull($data['description'] ?? null);
    }

    public function testTargetSlugIsUnique(): void
    {
        $this->getOrCreateDefaultTarget();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/targets', [
            'slug' => 'test-default',
            'name' => 'Duplicate',
        ]);
        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testTargetNameIsRequired(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/targets', [
            'slug' => 'no-name',
        ]);
        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUserCannotCreateATarget(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'POST', '/targets', [
            'slug' => 'forbidden',
            'name' => 'Forbidden',
        ]);
        $this->assertEquals(403, $response->getStatusCode());
    }
}
