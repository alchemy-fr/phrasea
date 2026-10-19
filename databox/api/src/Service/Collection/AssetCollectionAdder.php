<?php

declare(strict_types=1);

namespace App\Service\Collection;

use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Adds assets to a collection (or a story), without moving them.
 * Permissions are left to the caller.
 */
final readonly class AssetCollectionAdder
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Why the asset cannot be added to the collection, null when it can.
     */
    public function getRefusalReason(Collection $collection, Asset $asset): ?string
    {
        if ($asset->getWorkspaceId() !== $collection->getWorkspaceId()) {
            return sprintf('Asset "%s" does not belong to the destination workspace', $asset->getId());
        }
        if ($asset->getId() === $collection->getStoryAsset()?->getId()) {
            return 'Cannot add a story to itself';
        }

        return null;
    }

    /**
     * Adds the assets not in the collection yet: assets already in it are silently skipped.
     *
     * @param Asset[] $assets
     */
    public function add(Collection $collection, array $assets): void
    {
        $alreadyIn = $this->em->getRepository(CollectionAsset::class)->findAssetIdsInCollection(
            $collection->getId(),
            array_map(fn (Asset $asset): string => $asset->getId(), $assets),
        );

        foreach ($assets as $asset) {
            if (in_array($asset->getId(), $alreadyIn, true)) {
                continue;
            }

            $this->em->persist($asset->addToCollection($collection));
        }

        $this->em->flush();
    }
}
