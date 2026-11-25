<?php

declare(strict_types=1);

namespace App\Tests\Functional;

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
                'file' => new UploadedFile(__DIR__.'/../fixtures/32x32.jpg', '32x32.jpg', 'image/jpeg'),
            ]);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (201 !== $response->getStatusCode()) {
            dump($response->getContent());
        }

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));

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

    public function testAWmaIsStoredAsAudio(): void
    {
        $publication = $this->createPublication();
        $path = tempnam(sys_get_temp_dir(), 'wma');
        // The ASF Header Object GUID: libmagic names the container only ("video/x-ms-asf")
        file_put_contents($path, "\x30\x26\xB2\x75\x8E\x66\xCF\x11\xA6\xD9\x00\xAA\x00\x62\xCE\x6C\xEE\x01\x00\x00\x00\x00\x00\x00\x05\x00\x00\x00\x01\x02");

        try {
            $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/assets', [
                'publication_id' => $publication->getId(),
                'asset_id' => KeycloakClientTestMock::ADMIN_UID,
            ], [
                'file' => new UploadedFile($path, 'song.wma', 'video/x-ms-asf'),
            ]);
            $this->assertEquals(201, $response->getStatusCode(), $response->getContent());
        } finally {
            @unlink($path);
        }

        $this->clearEmBeforeApiCall();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/publications/'.$publication->getId());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $json['assets']);
        $this->assertSame('audio/x-ms-wma', $json['assets'][0]['mimeType']);
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
            'file' => new UploadedFile(__DIR__.'/../fixtures/empty.jpg', 'foo.jpg', 'image/jpeg'),
        ]);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUploadPlaylistGenerates400(): void
    {
        $publication = $this->createPublication();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/assets', [
            'publication_id' => $publication->getId(),
        ], [
            'file' => new UploadedFile(__DIR__.'/../fixtures/playlist.m3u8', 'video.mp4', 'video/mp4'),
        ]);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('HLS playlist', $response->getContent());
    }

    public function testUploadWithoutPublicationIdGenerates400(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/assets', [], [
            'file' => new UploadedFile(__DIR__.'/../fixtures/empty.jpg', 'foo.jpg', 'image/jpeg'),
        ]);
        $this->assertEquals(400, $response->getStatusCode());
    }
}
