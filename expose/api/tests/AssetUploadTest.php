<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class AssetUploadTest extends ApiTestCase
{
    public function testUploadAssetOK(): void
    {
        $id = $this->createPublication();

        $response = $this->request('POST', '/assets', [
            'publication_id' => $id,
            'asset_id' => '123',
        ], [
            'file' => new UploadedFile(__DIR__.'/fixtures/32x32.jpg', '32x32.jpg', 'image/jpeg'),
        ]);
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayHasKey('id', $json);
        $this->assertRegExp('#^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$#', $json['id']);
        $this->assertArrayHasKey('originalName', $json);
        $this->assertSame('32x32.jpg', $json['originalName']);
        $this->assertArrayHasKey('size', $json);
        $this->assertSame(846, $json['size']);
    }

    public function testUploadAssetWithoutFileGenerates400(): void
    {
        $id = $this->createPublication();

        $response = $this->request('POST', '/assets', [
            'publication_id' => $id,
        ]);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUploadEmptyFileGenerates400(): void
    {
        $id = $this->createPublication();

        $response = $this->request('POST', '/assets', [
            'publication_id' => $id,
        ], [
            'file' => new UploadedFile(__DIR__.'/fixtures/empty.jpg', 'foo.jpg', 'image/jpeg'),
        ]);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUploadWithoutPublicationIdGenerates400(): void
    {
        $response = $this->request('POST', '/assets', [], [
            'file' => new UploadedFile(__DIR__.'/fixtures/empty.jpg', 'foo.jpg', 'image/jpeg'),
        ]);
        $this->assertEquals(400, $response->getStatusCode());
    }
}
