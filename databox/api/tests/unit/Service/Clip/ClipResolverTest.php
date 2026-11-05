<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Clip;

use App\Entity\Basket\BasketAsset;
use App\Entity\Core\Asset;
use App\Service\Clip\ClipResolver;
use PHPUnit\Framework\TestCase;

class ClipResolverTest extends TestCase
{
    private const array ROI = ['crop' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.5]];
    private const array CLIP = ['timeRanges' => [['s' => 1.0, 'e' => 2.0]]];

    public function testAssetRoi(): void
    {
        $asset = new Asset();
        $this->assertNull(ClipResolver::forAsset($asset));

        $asset->setRoi(self::ROI);
        $this->assertSame(self::ROI, ClipResolver::forAsset($asset)->toArray());
        $this->assertNull(ClipResolver::forAsset($asset, true));
    }

    public function testBasketItemInheritsTheRoiOfItsAsset(): void
    {
        $asset = new Asset();
        $asset->setRoi(self::ROI);
        $item = self::createItem($asset);

        $this->assertSame(self::ROI, ClipResolver::forBasketAsset($item)->toArray());
        $this->assertNull(ClipResolver::forBasketAsset($item, true));
    }

    public function testClipOfTheItemReplacesTheRoi(): void
    {
        $asset = new Asset();
        $asset->setRoi(self::ROI);
        $item = self::createItem($asset);
        $item->setClip(self::CLIP);

        // Not intersected: the crop of the asset is not applied
        $this->assertSame(self::CLIP, ClipResolver::forBasketAsset($item)->toArray());
        $this->assertNull(ClipResolver::forBasketAsset($item, true));
    }

    public function testNothingToApply(): void
    {
        $item = self::createItem(new Asset());

        $this->assertNull(ClipResolver::forBasketAsset($item));
    }

    private static function createItem(Asset $asset): BasketAsset
    {
        $item = new BasketAsset();
        $item->setAsset($asset);

        return $item;
    }
}
