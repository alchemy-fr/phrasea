<?php

declare(strict_types=1);

namespace App\Service\Asset;

use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\CollectionAsset;
use App\Repository\Core\CollectionAssetRepository;
use App\Service\Storage\RenditionManager;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Picks the already built rendition (thumbnail or preview) of the first items
 * of a story, the images its contact sheet is made of. Item sources are never read.
 */
final readonly class StoryContactSheetInputResolver
{
    public const string INPUT_THUMBNAIL = 'thumbnail';
    public const string INPUT_PREVIEW = 'preview';
    public const array INPUTS = [self::INPUT_THUMBNAIL, self::INPUT_PREVIEW];

    public function __construct(
        private EntityManagerInterface $em,
        private RenditionManager $renditionManager,
        private FileFetcher $fileFetcher,
    ) {
    }

    /**
     * @param string $input one of self::INPUTS
     * @param int    $limit the number of cells of the sheet
     */
    public function resolve(Asset $story, string $input, int $limit): StoryContactSheetInputs
    {
        $storyCollection = $story->getStoryCollection();
        if (null === $storyCollection) {
            return new StoryContactSheetInputs([], false);
        }

        /** @var CollectionAssetRepository $repository */
        $repository = $this->em->getRepository(CollectionAsset::class);

        $files = [];
        $pending = false;
        foreach ($repository->findStoryContactSheetItemIds($storyCollection, $limit) as $itemId) {
            $item = $this->em->find(Asset::class, $itemId);
            if (!$item instanceof Asset) {
                continue;
            }

            $renditions = StoryRenditionPreference::sort(
                $item,
                $input,
                $this->renditionManager->getAssetRenditionsUsedAs($input, $item->getId()),
            );

            $built = false;
            foreach ($renditions as $rendition) {
                $file = $rendition->getFile();
                if (null === $file) {
                    continue;
                }
                $built = true;

                if (str_starts_with($file->getType() ?? '', 'image/') && $this->fileFetcher->isFetchable($file)) {
                    $files[] = $file;
                    continue 2;
                }
            }

            // The item ingest workflow will build it: wait for it rather than leaving a hole
            $source = $item->getSource();
            if (!$built
                && null !== $source
                && $this->fileFetcher->isFetchable($source)
                && !$this->hasBuiltRendition($item, $input)) {
                $pending = true;
            }
        }

        return new StoryContactSheetInputs($files, $pending);
    }

    /**
     * Whatever the rendition policy: a non-public rendition is built, but never used.
     */
    private function hasBuiltRendition(Asset $item, string $input): bool
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(AssetRendition::class, 'r')
            ->innerJoin('r.definition', 'd')
            ->andWhere('r.asset = :asset')
            ->andWhere('r.file IS NOT NULL')
            ->andWhere(sprintf('d.useAs%s = true', ucfirst($input)))
            ->setParameter('asset', $item->getId())
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
