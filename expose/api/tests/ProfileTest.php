<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;

class ProfileTest extends AbstractExposeTestCase
{
    public function testCreateProfileOK(): void
    {
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'POST', '/publication-profiles', [
            'name' => 'profile_1',
            'config' => [
                'layout' => 'download',
                'enabled' => false,
            ],
        ]);
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('name', $json);
        $this->assertEquals('profile_1', $json['name']);
        $this->assertEquals(AuthServiceClientTestMock::ADMIN_UID, $json['ownerId']);
        $this->assertArrayHasKey('config', $json);
        $this->assertEquals('download', $json['config']['layout']);
        $this->assertMatchesRegularExpression('#^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$#', $json['id']);
    }

    public function testListProfilesWithAcl(): void
    {
        $this->createProfile([
            'name' => 'profile_1',
        ]);
        $this->createProfile([
            'name' => 'profile_2',
        ]);

        $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'PUT', '/permissions/ace', [
            'userType' => 'user',
            'userId' => AuthServiceClientTestMock::USER_UID,
            'objectType' => 'profile',
            'mask' => PermissionInterface::VIEW,
        ]);

        $response = $this->request(AuthServiceClientTestMock::USER_TOKEN, 'GET', '/publication-profiles', []);
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertEquals(2, count($json));
        $this->assertEquals('profile_1', $json[0]['name']);
        $this->assertEquals('profile_2', $json[1]['name']);
    }

    public function testListProfilesAsAdmin(): void
    {
        $this->createProfile([
            'name' => 'profile_1',
        ]);
        $this->createProfile([
            'name' => 'profile_2',
        ]);

        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'GET', '/publication-profiles', []);
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertEquals(2, count($json));
        $this->assertEquals('profile_1', $json[0]['name']);
        $this->assertEquals('profile_2', $json[1]['name']);
    }

    public function testListProfilesWithoutPerms(): void
    {
        $this->createProfile([
            'name' => 'profile_1',
        ]);
        $this->createProfile([
            'name' => 'profile_2',
        ]);

        $response = $this->request(AuthServiceClientTestMock::USER_TOKEN, 'GET', '/publication-profiles', []);
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertEquals(0, count($json));
    }

    public function testCreateProfileWithoutNameWillGenerate400(): void
    {
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'POST', '/publication-profiles', []);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testGetProfileFromAdmin(): void
    {
        $id = $this->createProfile();
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'GET', '/publication-profiles/'.$id);
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('name', $json);
        $this->assertEquals(null, $json['ownerId']);
    }

    public function testGetProfileAsUser(): void
    {
        $id = $this->createProfile();
        $response = $this->request(AuthServiceClientTestMock::USER_TOKEN, 'GET', '/publication-profiles/'.$id);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGetProfileFromAnonymous(): void
    {
        $id = $this->createProfile(['enabled' => true]);
        $response = $this->request(null, 'GET', '/publication-profiles/'.$id);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
    }

    public function testDeleteProfileAsAdmin(): void
    {
        $id = $this->createProfile();
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'DELETE', '/publication-profiles/'.$id);
        $this->assertEquals(204, $response->getStatusCode());
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'GET', '/publication-profiles/'.$id);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDeleteProfileAsAnonymous(): void
    {
        $id = $this->createProfile();
        $response = $this->request(null, 'DELETE', '/publication-profiles/'.$id);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDeleteProfileAsUser(): void
    {
        $id = $this->createProfile();
        $response = $this->request(AuthServiceClientTestMock::USER_TOKEN, 'DELETE', '/publication-profiles/'.$id);
        $this->assertEquals(403, $response->getStatusCode());
    }
}
