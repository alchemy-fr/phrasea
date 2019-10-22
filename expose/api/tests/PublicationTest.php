<?php

declare(strict_types=1);

namespace App\Tests;

class PublicationTest extends ApiTestCase
{
    public function testCreatePublicationOK(): void
    {
        $response = $this->request('POST', '/publications', [
            'name' => 'Foo',
            'layout' => 'download',
        ]);
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('name', $json);
        $this->assertEquals('Foo', $json['name']);
        $this->assertRegExp('#^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$#', $json['id']);
    }

    public function testCreatePublicationWithoutNameWillGenerate400(): void
    {
        $response = $this->request('POST', '/publications', []);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testGetPublication(): void
    {
        $id = $this->createPublication();
        $response = $this->request('GET', '/publications/'.$id);
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('name', $json);
    }
}
