<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AssetUploadTest extends AbstractExposeTestCase
{
    public function testUploadAssetOK(): void
    {
        $id = $this->createPublication();

        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'POST', '/assets', [
            'publication_id' => $id,
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
        $this->assertMatchesRegularExpression('#^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$#', $json['id']);
        $this->assertArrayHasKey('size', $json);
        $this->assertSame(846, $json['size']);

        $this->clearEmBeforeApiCall();

        // Test the asset is added to the publication
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'GET', '/publications/'.$id);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);

        $this->assertEquals('Foo', $json['title']);
        $this->assertEquals(1, count($json['assets']));
        $this->assertMatchesRegularExpression('#^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$#', $json['assets'][0]['id']);
        $this->assertEquals('image/jpeg', $json['assets'][0]['asset']['mimeType']);
    }

    public function testUploadAssetWithoutFileGenerates400(): void
    {
        $id = $this->createPublication();

        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'POST', '/assets', [
            'publication_id' => $id,
        ]);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUploadEmptyFileGenerates400(): void
    {
        $id = $this->createPublication();

        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'POST', '/assets', [
            'publication_id' => $id,
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
