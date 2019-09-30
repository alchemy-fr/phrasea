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

    public function __construct(
        EntityManagerInterface $em,
        int $assetDaysRetention
    ) {
        $this->em = $em;
        $this->assetDaysRetention = $assetDaysRetention;
    }

    public function createAsset(
        string $path,
        string $mimeType,
        string $originalName,
        int $size,
        string $userId
    ): Asset {
        $asset = new Asset();
        $asset->setUserId($userId);
        $asset->setPath($path);
        $asset->setMimeType($mimeType);
        $asset->setOriginalName($originalName);
        $asset->setSize($size);

        $this->em->persist($asset);
        $this->em->flush();

        return $asset;
    }

    public function getTotalSize(array $assetIds): int
    {
        return $this->em
            ->getRepository(Asset::class)
            ->getAssetsTotalSize($assetIds);
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
            $this->em->remove($asset);
        }
        $this->em->flush();
    }
}
