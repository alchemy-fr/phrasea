<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Asset;
use App\Storage\AssetManager;
use App\Storage\FileStorageManager;
use Doctrine\ORM\EntityManagerInterface;

class AssetDownloadTest extends ApiTestCase
{
    const SAMPLE_FILE = __DIR__.'/fixtures/32x32.jpg';
    private $assetId;

    public function testAssetDownloadOK(): void
    {
        $this->commitAsset();
        [$response, $contents] = $this->requestDownload('secret_token');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/jpeg', $response->headers->get('Content-Type'));

        $this->assertEquals(file_get_contents(self::SAMPLE_FILE), $contents);
    }

    public function testAssetDownloadWithoutToken(): void
    {
        [$response] = $this->requestDownload(null);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAssetDownloadWithInvalidBearerToken(): void
    {
        $this->commitAsset();
        [$response] = $this->requestDownload('xxx', 'Bearer');
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAssetDownloadWithValidBearerToken(): void
    {
        $this->commitAsset();
        [$response] = $this->requestDownload('user@alchemy.fr', 'Bearer');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAssetDownloadWithAdminBearerToken(): void
    {
        $this->commitAsset();
        [$response] = $this->requestDownload('admin@alchemy.fr', 'Bearer');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAssetDownloadWithInvalidAssetToken(): void
    {
        $this->commitAsset();
        [$response] = $this->requestDownload('invalid_asset_token');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testUnCommittedAssetDownload(): void
    {
        [$response] = $this->requestDownload('admin@alchemy.fr', 'Bearer');
        $this->assertEquals(403, $response->getStatusCode());
    }

    private function requestDownload(?string $accessToken, $authType = 'AssetToken'): array
    {
        $token = null;
        if (null !== $accessToken) {
            $token = [$authType, $accessToken];
        }

        ob_start();
        $response = $this->request($token, 'GET', sprintf('/assets/%s/download', $this->assetId));
        $contents = ob_get_contents();
        ob_end_clean();

        return [
            $response,
            $contents,
        ];
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
        $asset = $assetManager->createAsset($path, 'image/jpeg', 'foo.jpg', 846);

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
