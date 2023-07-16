<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\AuthBundle\Tests\Client\OAuthClientTestMock;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AssetUploadTest extends AbstractUploaderTestCase
{
    public function testUploadAssetOK(): void
    {
        $this->markTestIncomplete();

        return;
        $response = $this->request(OAuthClientTestMock::ADMIN_TOKEN, 'POST', '/assets', [], [
            'file' => new UploadedFile(__DIR__.'/fixtures/32x32.jpg', '32x32.jpg', 'image/jpeg'),
        ]);
        $json = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayHasKey('id', $json);
        $this->assertMatchesUuid($json['id']);
        $this->assertArrayHasKey('originalName', $json);
        $this->assertSame('32x32.jpg', $json['originalName']);
        $this->assertArrayHasKey('size', $json);
        $this->assertSame(846, $json['size']);
    }

    public function testUploadAssetWithAnonymousUser(): void
    {
        $response = $this->request(null, 'POST', '/assets', [], [
            'file' => new UploadedFile(__DIR__.'/fixtures/32x32.jpg', '32x32.jpg', 'image/jpeg'),
        ]);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testUploadAssetWithInvalidToken(): void
    {
        $response = $this->request('invalid_token', 'POST', '/assets', [], [
            'file' => new UploadedFile(__DIR__.'/fixtures/32x32.jpg', '32x32.jpg', 'image/jpeg'),
        ]);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testUploadAssetWithoutFileGenerates400(): void
    {
        $response = $this->request(OAuthClientTestMock::ADMIN_TOKEN, 'POST', '/assets');
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUploadEmptyFileGenerates400(): void
    {
        $response = $this->request(OAuthClientTestMock::ADMIN_TOKEN, 'POST', '/assets', [], [
            'file' => new UploadedFile(__DIR__.'/fixtures/empty.jpg', 'foo.jpg', 'image/jpeg'),
        ]);
        $this->assertEquals(400, $response->getStatusCode());
    }
}
