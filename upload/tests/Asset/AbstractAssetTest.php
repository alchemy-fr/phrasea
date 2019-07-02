<?php

declare(strict_types=1);

namespace App\Tests\Asset;

use App\Entity\Asset;
use App\Storage\AssetManager;
use App\Storage\FileStorageManager;
use App\Tests\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FileExistsException;

abstract class AbstractAssetTest extends ApiTestCase
{
    const SAMPLE_FILE = __DIR__.'/../fixtures/32x32.jpg';
    protected $assetId;

    protected function commitAsset(string $token = 'secret_token')
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
        try {
            $storageManager->storeStream($path, $stream);
        } catch (FileExistsException $e) {
            $storageManager->delete($path);
            $storageManager->storeStream($path, $stream);
        }
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
