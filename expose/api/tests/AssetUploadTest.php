<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AssetUploadTest extends AbstractExposeTestCase
{
    public function testUploadAssetOK(): void
    {
        $publication = $this->createPublication();
        $id = $publication->getId();

        $response = $this->request(
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            'POST',
            '/assets',
            [
                'publication_id' => $publication->getId(),
                'asset_id' => KeycloakClientTestMock::ADMIN_UID,
            ], [
                'file' => new UploadedFile(__DIR__.'/fixtures/32x32.jpg', '32x32.jpg', 'image/jpeg'),
            ]);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (201 !== $response->getStatusCode()) {
            dump($response->getContent());
        }

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayHasKey('id', $json);
        $this->assertMatchesUuid($json['id']);
        $this->assertArrayHasKey('size', $json);
        $this->assertSame(846, $json['size']);

        $this->clearEmBeforeApiCall();

        // Test the asset is added to the publication
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/publications/'.$id);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals('Foo', $json['title']);
        $this->assertCount(1, $json['assets']);
        $this->assertMatchesUuid($json['assets'][0]['id']);
        $this->assertEquals('image/jpeg', $json['assets'][0]['mimeType']);
    }

    public function testUploadAssetWithoutFileGenerates400(): void
    {
        $publication = $this->createPublication();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/assets', [
            'publication_id' => $publication->getId(),
        ]);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUploadEmptyFileGenerates400(): void
    {
        $publication = $this->createPublication();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/assets', [
            'publication_id' => $publication->getId(),
        ], [
            'file' => new UploadedFile(__DIR__.'/fixtures/empty.jpg', 'foo.jpg', 'image/jpeg'),
        ]);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUploadWithoutPublicationIdGenerates400(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/assets', [], [
            'file' => new UploadedFile(__DIR__.'/fixtures/empty.jpg', 'foo.jpg', 'image/jpeg'),
        ]);
        $this->assertEquals(400, $response->getStatusCode());
    }
}
