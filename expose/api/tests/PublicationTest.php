<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\RemoteAuthBundle\Security\RemoteAuthenticatorClientTestMock;

class PublicationTest extends AbstractTestCase
{
    public function testCreatePublicationOK(): void
    {
        $response = $this->request(RemoteAuthenticatorClientTestMock::ADMIN_TOKEN, 'POST', '/publications', [
            'title' => 'Foo',
            'layout' => 'download',
        ]);
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('title', $json);
        $this->assertEquals('Foo', $json['title']);
        $this->assertRegExp('#^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$#', $json['id']);
    }

    public function testCreatePublicationWithoutTitleWillGenerate400(): void
    {
        $response = $this->request(RemoteAuthenticatorClientTestMock::ADMIN_TOKEN, 'POST', '/publications', []);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testGetPublication(): void
    {
        $id = $this->createPublication();
        $response = $this->request(RemoteAuthenticatorClientTestMock::ADMIN_TOKEN, 'GET', '/publications/'.$id);
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('title', $json);
    }
}
