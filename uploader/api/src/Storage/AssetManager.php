<?php

declare(strict_types=1);

namespace App\Storage;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Consumer\Handler\DeleteAssetFile;
use App\Entity\Asset;
use App\Entity\Target;
use App\Entity\UploadLink;
use App\Repository\UploadLinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class AssetManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UploadLinkRepository $uploadLinkRepository,
        private readonly MessageBusInterface $bus,
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
        ?UploadLink $uploadLink = null,
    ): Asset {
        $asset = new Asset();
        $asset->setTarget($target);
        $asset->setUserId($userId);
        $asset->setPath($path);
        $asset->setMimeType($mimeType);
        $asset->setOriginalName($originalName);
        $asset->setSize($size);
        $asset->setData($data);
        $asset->setUploadLink($uploadLink);

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
            if ($asset->isCommitted()) {
                $this->em->remove($asset);
            } else {
                $this->removeUncommitted($asset);
            }
        }
        $this->em->flush();
    }

    /**
     * Deletes a file not committed yet and gives back its quota, only if this call is the one that deleted it.
     *
     * The row is deleted in SQL rather than through the ORM, which ignores how many rows its DELETE affected:
     * concurrent deletions of the same file would otherwise each give back its quota.
     *
     * @return bool false when the file was already deleted or has been committed meanwhile
     */
    public function removeUncommitted(Asset $asset): bool
    {
        $connection = $this->em->getConnection();

        $deleted = $connection->transactional(function () use ($connection, $asset): bool {
            $row = $connection->executeQuery(
                'DELETE FROM asset WHERE id = :id AND commit_id IS NULL RETURNING size, upload_link_id',
                ['id' => $asset->getId()],
            )->fetchAssociative();

            if (false === $row) {
                return false;
            }

            if (null !== $row['upload_link_id']) {
                $this->uploadLinkRepository->release((string) $row['upload_link_id'], (int) $row['size']);
            }

            return true;
        });

        if (!$deleted) {
            return false;
        }

        $this->em->detach($asset);
        $this->bus->dispatch(new DeleteAssetFile($asset->getPath()));

        return true;
    }
}
