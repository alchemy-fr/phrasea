<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AssetUploadTest extends AbstractUploaderTestCase
{
    public function testUploadAssetOK(): void
    {
        $client = self::createClient();

        $response = $client->request('POST', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
                'Content-Type' => 'multipart/form-data',
            ],
            'extra' => [
                'files' => [
                    'file' => new UploadedFile(__DIR__.'/fixtures/32x32.jpg', '32x32.jpg', 'image/jpeg'),
                ],
            ],
        ]);
        $this->assertSame(400, $response->getStatusCode());
        $data = $response->toArray(false);
        $this->assertStringContainsString('"targetId" or "targetSlug" is required', $data['hydra:description']);

        $target = $this->getOrCreateDefaultTarget();
        $response = $client->request('POST', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
                'Content-Type' => 'multipart/form-data',
            ],
            'extra' => [
                'files' => [
                    'file' => new UploadedFile(__DIR__.'/fixtures/32x32.jpg', '32x32.jpg', 'image/jpeg'),
                ],
                'parameters' => [
                    'targetSlug' => $target->getSlug(),
                ],
            ],
        ]);

        $this->assertSame(201, $response->getStatusCode());
        $data = $response->toArray();

        $this->assertArrayHasKey('id', $data);
        $this->assertMatchesUuid($data['id']);
        $this->assertArrayHasKey('originalName', $data);
        $this->assertSame('32x32.jpg', $data['originalName']);
        $this->assertArrayHasKey('size', $data);
        $this->assertSame(846, $data['size']);
    }

    public function testUploadAssetWithAnonymousUser(): void
    {
        $client = self::createClient();

        $response = $client->request('POST', '/assets', [
            'headers' => [
                'Content-Type' => 'multipart/form-data',
            ],
            'extra' => [
                'files' => [
                    'file' => new UploadedFile(__DIR__.'/fixtures/32x32.jpg', '32x32.jpg', 'image/jpeg'),
                ],
            ],
        ]);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testUploadAssetWithInvalidToken(): void
    {
        $client = self::createClient();

        $response = $client->request('POST', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer invalid_token',

                'Content-Type' => 'multipart/form-data',
            ],
            'extra' => [
                'files' => [
                    'file' => new UploadedFile(__DIR__.'/fixtures/32x32.jpg', '32x32.jpg', 'image/jpeg'),
                ],
            ],
        ]);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testUploadAssetWithoutFileGenerates400(): void
    {
        $client = self::createClient();

        $target = $this->getOrCreateDefaultTarget();
        $response = $client->request('POST', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
                'Content-Type' => 'multipart/form-data',
            ],
            'extra' => [
                'parameters' => [
                    'targetSlug' => $target->getSlug(),
                ],
            ],
        ]);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUploadEmptyFileGenerates400(): void
    {
        $client = self::createClient();

        $target = $this->getOrCreateDefaultTarget();
        $response = $client->request('POST', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
                'Content-Type' => 'multipart/form-data',
            ],
            'extra' => [
                'files' => [
                    'file' => new UploadedFile(__DIR__.'/fixtures/empty.jpg', 'foo.jpg', 'image/jpeg'),
                ],
                'parameters' => [
                    'targetSlug' => $target->getSlug(),
                ],
            ],
        ]);
        $this->assertEquals(400, $response->getStatusCode());
    }
}
