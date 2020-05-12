<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;

class PublicationTest extends AbstractExposeTestCase
{
    public function testCreatePublicationOK(): void
    {
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'POST', '/publications', [
            'title' => 'Foo',
            'config' => [
                'layout' => 'download',
            ],
        ]);
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('title', $json);
        $this->assertEquals('Foo', $json['title']);
        $this->assertEquals('123', $json['ownerId']);
        $this->assertArrayHasKey('config', $json);
        $this->assertEquals('download', $json['config']['layout']);
        $this->assertRegExp('#^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$#', $json['id']);
    }

    public function testListPublications(): void
    {
        $this->createPublication([
            'title' => 'Pub #1',
            'enabled' => true,
            'publicly_listed' => true,
        ]);
        $this->createPublication([
            'title' => 'Pub #2',
            'enabled' => true,
            'publicly_listed' => true,
        ]);

        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'GET', '/publications', []);
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertEquals(2, count($json));
        $this->assertEquals('Pub #1', $json[0]['title']);
        $this->assertEquals('Pub #2', $json[1]['title']);
    }

    public function testCreatePublicationWithoutTitleWillGenerate400(): void
    {
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'POST', '/publications', []);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testGetPublicationFromAdmin(): void
    {
        $id = $this->createPublication();
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'GET', '/publications/'.$id);
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('title', $json);
        $this->assertEquals(null, $json['ownerId']);
    }

    public function testGetPublicationFromAnonymous(): void
    {
        $id = $this->createPublication(['enabled' => true]);
        $response = $this->request(null, 'GET', '/publications/'.$id);
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayNotHasKey('ownerId', $json);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('title', $json);
    }

    public function testGetNonEnabledPublicationFromAdmin(): void
    {
        $id = $this->createPublication(['enabled' => false]);
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'GET', '/publications/'.$id);
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertEquals(null, $json['ownerId']);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('title', $json);
    }

    public function testGetNonEnabledPublicationFromAnonymous(): void
    {
        $id = $this->createPublication(['enabled' => false]);
        $response = $this->request(null, 'GET', '/publications/'.$id);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testDeletePublicationAsAdmin(): void
    {
        $id = $this->createPublication();
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'DELETE', '/publications/'.$id);
        $this->assertEquals(204, $response->getStatusCode());
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'GET', '/publications/'.$id);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDeletePublicationAsAnonymous(): void
    {
        $id = $this->createPublication();
        $response = $this->request(null, 'DELETE', '/publications/'.$id);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDeletePublicationAsUser(): void
    {
        $id = $this->createPublication();
        $response = $this->request(AuthServiceClientTestMock::USER_TOKEN, 'DELETE', '/publications/'.$id);
        $this->assertEquals(403, $response->getStatusCode());
    }
}
