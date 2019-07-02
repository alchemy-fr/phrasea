<?php

declare(strict_types=1);

namespace App\Storage;

use App\Entity\Asset;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FileNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssetManager
{
    /**
     * @var EntityManagerInterface
     */
    private $em;
    /**
     * @var int
     */
    private $assetDaysRetention;
    /**
     * @var FileStorageManager
     */
    private $storageManager;

    public function __construct(
        EntityManagerInterface $em,
        int $assetDaysRetention,
        FileStorageManager $storageManager
    ) {
        $this->em = $em;
        $this->assetDaysRetention = $assetDaysRetention;
        $this->storageManager = $storageManager;
    }

    public function createAsset(
        string $path,
        string $mimeType,
        string $originalName,
        int $size
    ): Asset {
        $asset = new Asset();
        $asset->setPath($path);
        $asset->setMimeType($mimeType);
        $asset->setOriginalName($originalName);
        $asset->setSize($size);

        $this->em->persist($asset);
        $this->em->flush();

        return $asset;
    }

    public function findAsset(string $id): Asset
    {
        $asset = $this->em->find(Asset::class, $id);
        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException('Asset '.$id.' not found');
        }

        return $asset;
    }

    public function cleanAssets(?int $assetDaysRetention = null): void
    {
        $assets = $this->em->getRepository(Asset::class)->findExpiredAssets($assetDaysRetention ?? $this->assetDaysRetention);

        foreach ($assets as $asset) {
            try {
                $this->storageManager->delete($asset->getPath());
            } catch (FileNotFoundException $e) {
            }
            $this->em->remove($asset);
            $this->em->flush();
        }
    }
}
