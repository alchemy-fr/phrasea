<?php

declare(strict_types=1);

namespace App\Service\Asset;

use App\Entity\Core\Asset;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The cover of a story is one of its items: the story shares the item source
 * file (the same File), its renditions being generated from it. The item owns
 * the file: its analysis and duplicate links are not reset, and the file is
 * only removed once no asset references it.
 */
final readonly class StoryCoverManager
{
    public function __construct(
        private AssetManager $assetManager,
    ) {
    }

    /**
     * The caller checks that $cover belongs to the story and may be read.
     */
    public function setCover(Asset $story, Asset $cover): void
    {
        if (null === $story->getStoryCollection()) {
            throw new BadRequestHttpException('Asset is not a story.');
        }

        $file = $cover->getSource();
        if (null === $file) {
            throw new BadRequestHttpException('Cover asset has no source file.');
        }

        if ($story->getSource() === $file) {
            return;
        }

        $this->assetManager->assignNewAssetSourceFile($story, $file, sharedFile: true);
    }
}
