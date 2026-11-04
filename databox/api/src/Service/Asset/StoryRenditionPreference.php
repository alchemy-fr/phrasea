<?php

declare(strict_types=1);

namespace App\Service\Asset;

use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Model\AssetTypeEnum;

/**
 * A story with a cover has renditions of both origins: those derived from the
 * cover and those targeted at stories only (the contact sheet). The cover gives
 * the thumbnail, the story renditions the animated (hover) thumbnail.
 */
final class StoryRenditionPreference
{
    /**
     * @param AssetRendition[] $renditions ordered by priority
     *
     * @return AssetRendition[] the same renditions, the preferred ones first for $usedAs
     */
    public static function sort(Asset $asset, string $usedAs, array $renditions): array
    {
        if (!$asset->isStory() || null === $asset->getSource()) {
            return $renditions;
        }

        $preferStoryTarget = match ($usedAs) {
            'thumbnail' => false,
            'animatedThumbnail' => true,
            default => null,
        };
        if (null === $preferStoryTarget) {
            return $renditions;
        }

        $preferred = [];
        $others = [];
        foreach ($renditions as $rendition) {
            $storyTarget = AssetTypeEnum::Story === $rendition->getDefinition()?->getTarget();
            if ($storyTarget === $preferStoryTarget) {
                $preferred[] = $rendition;
            } else {
                $others[] = $rendition;
            }
        }

        return [...$preferred, ...$others];
    }
}
