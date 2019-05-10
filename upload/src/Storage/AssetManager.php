<?php

declare(strict_types=1);

namespace App\Storage;

use App\Entity\Asset;
use Doctrine\ORM\EntityManagerInterface;

class AssetManager
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function createAsset(
        string $path,
        string $mimeType,
        string $originalName,
        int $size,
        string $id = null
    ): Asset
    {
        $asset = new Asset($id);
        $asset->setPath($path);
        $asset->setMimeType($mimeType);
        $asset->setOriginalName($originalName);
        $asset->setSize($size);

        $this->em->persist($asset);
        $this->em->flush();

        return $asset;
    }
}
