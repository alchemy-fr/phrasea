<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Asset;
use App\Storage\AssetManager;
use App\Storage\FileStorageManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class AssetGetTest extends ApiTestCase
{
    const SAMPLE_FILE = __DIR__.'/fixtures/32x32.jpg';
    private $assetId;

    public function testAssetGetOK(): void
    {
        $this->commitAsset();
        $response = $this->requestGet('secret_token');

        $contents = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertEquals('baz.jpg', $contents['originalName']);
        $this->assertEquals(['foo' => 'bar'], $contents['formData']);
        $this->assertEquals(846, $contents['size']);
        $this->assertEquals('image/jpeg', $contents['mimeType']);
        $this->assertArrayNotHasKey('token', $contents);
        $this->assertArrayNotHasKey('path', $contents);
        $this->assertArrayHasKey('createdAt', $contents);
    }

    public function testAssetGetWithoutToken(): void
    {
        $response = $this->requestGet(null);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAssetGetWithInvalidBearerToken(): void
    {
        $this->commitAsset();
        $response = $this->requestGet('xxx', 'Bearer');
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAssetGetWithValidBearerToken(): void
    {
        $this->commitAsset();
        $response = $this->requestGet('user@alchemy.fr', 'Bearer');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAssetGetWithAdminBearerToken(): void
    {
        $this->commitAsset();
        $response = $this->requestGet('admin@alchemy.fr', 'Bearer');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAssetGetWithInvalidAssetToken(): void
    {
        $this->commitAsset();
        $response = $this->requestGet('invalid_asset_token');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testUnCommittedAssetGet(): void
    {
        $response = $this->requestGet('admin@alchemy.fr', 'Bearer');
        $this->assertEquals(403, $response->getStatusCode());
    }

    private function requestGet(?string $accessToken, $authType = 'AssetToken'): Response
    {
        $token = null;
        if (null !== $accessToken) {
            $token = [$authType, $accessToken];
        }

        return $this->request($token, 'GET', sprintf('/assets/%s', $this->assetId));
    }

    private function commitAsset(string $token = 'secret_token')
    {
        /** @var EntityManagerInterface $em */
        $em = self::$container->get(EntityManagerInterface::class);
        $em
            ->getRepository(Asset::class)
            ->attachFormDataAndToken([$this->assetId], ['foo' => 'bar'], $token);

        $asset = $em->find(Asset::class, $this->assetId);
        $em->refresh($asset);
    }

    private function createAsset(): Asset
    {
        /** @var AssetManager $assetManager */
        $assetManager = self::$container->get(AssetManager::class);
        $storageManager = self::$container->get(FileStorageManager::class);
        $realPath = self::SAMPLE_FILE;
        $path = 'test/foo.jpg';
        $asset = $assetManager->createAsset($path, 'image/jpeg', 'baz.jpg', 846);

        $stream = fopen($realPath, 'r+');
        $storageManager->delete($path);
        $storageManager->storeStream($path, $stream);
        fclose($stream);

        return $asset;
    }

    protected function setUp()
    {
        parent::setUp();

        $asset = $this->createAsset();
        $this->assetId = $asset->getId();
    }
}
