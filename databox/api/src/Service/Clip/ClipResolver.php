<?php

declare(strict_types=1);

namespace App\Service\Clip;

use App\Entity\Basket\BasketAsset;
use App\Entity\Core\Asset;
use App\Model\MediaClip;

/**
 * The clip a file is cut to when it is exported or published: the clip of the basket item
 * replaces the region of interest of its asset as a whole (they are not intersected).
 */
final class ClipResolver
{
    /**
     * @param bool $ignore the whole files are wanted
     */
    public static function forBasketAsset(BasketAsset $basketAsset, bool $ignore = false): ?MediaClip
    {
        if ($ignore) {
            return null;
        }

        return $basketAsset->getMediaClip() ?? self::forAsset($basketAsset->getAsset());
    }

    /**
     * @param bool $ignore the whole files are wanted
     */
    public static function forAsset(Asset $asset, bool $ignore = false): ?MediaClip
    {
        if ($ignore) {
            return null;
        }

        return $asset->getMediaClip();
    }
}
