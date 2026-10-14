<?php

declare(strict_types=1);

namespace App\Service\Vector;

use Alchemy\StorageBundle\Util\FileUtil;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetEmbedding;
use App\Service\Asset\FileFetcher;
use App\Service\Image\ImageDownscaler;
use App\Service\Storage\RenditionManager;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AssetEmbeddingManager
{
    final public const string DEFAULT_RENDITION = 'preview';

    /**
     * CLIP works on 224px inputs: sending more than this is useless, and the embedder caps uploads at 2 MB.
     */
    final public const int MAX_IMAGE_SIZE = 1024;
    private const int MAX_UPLOAD_BYTES = 1024 * 1024;

    public function __construct(
        private EntityManagerInterface $em,
        private RenditionManager $renditionManager,
        private FileFetcher $fileFetcher,
        private EmbedderClient $embedderClient,
        private ImageDownscaler $imageDownscaler,
    ) {
    }

    public function embedAsset(Asset $asset, string $renditionName = self::DEFAULT_RENDITION, bool $force = false): bool
    {
        $rendition = $this->renditionManager->getAssetRenditionByName($asset->getId(), $renditionName);
        $file = $rendition?->getFile();
        if (null === $file || !FileUtil::isImageType($file->getType())) {
            return false;
        }

        $path = $this->fileFetcher->getFile($file);
        $uploadPath = $this->imageDownscaler->downscale($path, self::MAX_IMAGE_SIZE, self::MAX_UPLOAD_BYTES);
        try {
            $result = $this->embedderClient->embedImageFile($uploadPath);
        } finally {
            if ($uploadPath !== $path) {
                @unlink($uploadPath);
            }
        }

        $existingEmbedding = $this->em->getRepository(AssetEmbedding::class)
            ->findOneBy(['asset' => $asset->getId()]);
        if (null !== $existingEmbedding && !$force) {
            return false;
        }

        $embedding = $existingEmbedding ?? new AssetEmbedding();
        $embedding->setAsset($asset);
        $embedding->setVector($result['vector']);
        $embedding->setModel($result['model']);

        $this->em->persist($embedding);
        $this->em->flush();

        return true;
    }

    public function getAssetVector(Asset $asset): ?array
    {
        $embedding = $this->em->getRepository(AssetEmbedding::class)
            ->findOneBy(['asset' => $asset->getId()]);

        return $embedding?->getVector();
    }
}
