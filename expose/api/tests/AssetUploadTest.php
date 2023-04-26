<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AssetUploadTest extends AbstractExposeTestCase
{
    public function testUploadAssetOK(): void
    {
        $publication = $this->createPublication();
        $id = $publication->getId();

        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'POST', '/assets', [
            'publication_id' => $publication->getId(),
            'asset_id' => AuthServiceClientTestMock::ADMIN_UID,
        ], [
            'file' => new UploadedFile(__DIR__.'/fixtures/32x32.jpg', '32x32.jpg', 'image/jpeg'),
        ]);
        $json = json_decode($response->getContent(), true);
        if (500 === $response->getStatusCode()) {
            var_dump($response->getContent());
        }

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayHasKey('id', $json);
        $this->assertMatchesUuid($json['id']);
        $this->assertArrayHasKey('size', $json);
        $this->assertSame(846, $json['size']);

        $this->clearEmBeforeApiCall();

        // Test the asset is added to the publication
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'GET', '/publications/'.$id);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);

        $this->assertEquals('Foo', $json['title']);
        $this->assertCount(1, $json['assets']);
        $this->assertMatchesUuid($json['assets'][0]['id']);
        $this->assertEquals('image/jpeg', $json['assets'][0]['mimeType']);
    }

    public function testUploadAssetWithoutFileGenerates400(): void
    {
        $publication = $this->createPublication();

        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'POST', '/assets', [
            'publication_id' => $publication->getId(),
        ]);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUploadEmptyFileGenerates400(): void
    {
        $publication = $this->createPublication();

        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'POST', '/assets', [
            'publication_id' => $publication->getId(),
        ], [
            'file' => new UploadedFile(__DIR__.'/fixtures/empty.jpg', 'foo.jpg', 'image/jpeg'),
        ]);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUploadWithoutPublicationIdGenerates400(): void
    {
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'POST', '/assets', [], [
            'file' => new UploadedFile(__DIR__.'/fixtures/empty.jpg', 'foo.jpg', 'image/jpeg'),
        ]);
        $this->assertEquals(400, $response->getStatusCode());
    }
}
