<?php

declare(strict_types=1);

namespace App\Storage;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Entity\Asset;
use App\Entity\Target;
use Doctrine\ORM\EntityManagerInterface;

class AssetManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly int $assetDaysRetention,
    ) {
    }

    public function createAsset(
        Target $target,
        string $path,
        string $mimeType,
        string $originalName,
        int $size,
        string $userId,
        ?array $data = null,
    ): Asset {
        $asset = new Asset();
        $asset->setTarget($target);
        $asset->setUserId($userId);
        $asset->setPath($path);
        $asset->setMimeType($mimeType);
        $asset->setOriginalName($originalName);
        $asset->setSize($size);
        $asset->setData($data);

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
        return DoctrineUtil::findStrict($this->em, Asset::class, $id, throw404: true);
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
