<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Asset;
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
                    'file' => new UploadedFile(__DIR__.'/../fixtures/32x32.jpg', '32x32.jpg', 'image/jpeg'),
                ],
            ],
        ]);
        $this->assertSame(400, $response->getStatusCode());
        $data = $response->toArray(false);
        $this->assertStringContainsString('"targetId" or "targetSlug" is required', $data['detail']);

        $target = $this->getOrCreateDefaultTarget();
        $response = $client->request('POST', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
                'Content-Type' => 'multipart/form-data',
            ],
            'extra' => [
                'files' => [
                    'file' => new UploadedFile(__DIR__.'/../fixtures/32x32.jpg', '32x32.jpg', 'image/jpeg'),
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

    public function testOnlyTheKnownDataIsKept(): void
    {
        $target = $this->getOrCreateDefaultTarget();
        $response = self::createClient()->request('POST', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
                'Content-Type' => 'multipart/form-data',
            ],
            'extra' => [
                'files' => [
                    'file' => new UploadedFile(__DIR__.'/../fixtures/32x32.jpg', '32x32.jpg', 'image/jpeg'),
                ],
                'parameters' => [
                    'targetSlug' => $target->getSlug(),
                    'data' => [
                        'targetAsset' => 'b0a2b1e0-6bd5-4f6a-9a1f-2a3c4d5e6f70',
                        'targetRendition' => 'c1b3c2f1-7ce6-4a7b-8b2a-3b4d5e6f7a81',
                        'collectionId' => 'd2c4d3a2-8df7-4b8c-9c3b-4c5e6f7a8b92',
                    ],
                ],
            ],
        ]);
        $this->assertSame(201, $response->getStatusCode(), $response->getContent(false));

        $em = self::getEntityManager();
        $em->clear();
        $asset = $em->find(Asset::class, $response->toArray()['id']);
        $this->assertSame([
            'targetAsset' => 'b0a2b1e0-6bd5-4f6a-9a1f-2a3c4d5e6f70',
            'targetRendition' => 'c1b3c2f1-7ce6-4a7b-8b2a-3b4d5e6f7a81',
        ], $asset->getData());
        $this->assertFalse($asset->isFromUploadLink());
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
                    'file' => new UploadedFile(__DIR__.'/../fixtures/32x32.jpg', '32x32.jpg', 'image/jpeg'),
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
                    'file' => new UploadedFile(__DIR__.'/../fixtures/32x32.jpg', '32x32.jpg', 'image/jpeg'),
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
                    'file' => new UploadedFile(__DIR__.'/../fixtures/empty.jpg', 'foo.jpg', 'image/jpeg'),
                ],
                'parameters' => [
                    'targetSlug' => $target->getSlug(),
                ],
            ],
        ]);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUploadPlaylistGenerates400(): void
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
                    'file' => new UploadedFile(__DIR__.'/../fixtures/playlist.m3u8', 'video.mp4', 'video/mp4'),
                ],
                'parameters' => [
                    'targetSlug' => $target->getSlug(),
                ],
            ],
        ]);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('HLS playlist', $response->toArray(false)['detail']);
    }
}
