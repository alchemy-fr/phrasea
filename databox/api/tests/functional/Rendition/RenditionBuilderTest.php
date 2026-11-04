<?php

declare(strict_types=1);

namespace App\Tests\Functional\Rendition;

use App\Service\Asset\RenditionBuilder;
use App\Service\Storage\RenditionManager;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Api\Rendition\RenditionTestTrait;

final class RenditionBuilderTest extends AbstractDataboxTestCase
{
    use RenditionTestTrait;

    public function testPickSourceRenditionIsCreatedWhenMissing(): void
    {
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('pick-source');
        $main = $defaults->renditionDefinitions['main'];

        $source = $this->createUrlFile($workspace);
        $asset = $this->createAsset(['workspace' => $workspace]);
        $asset->setSource($source);
        self::getEntityManager()->flush();

        self::getService(RenditionBuilder::class)->buildRendition($main, $asset);

        $rendition = self::getService(RenditionManager::class)->getAssetRenditionByDefinition($asset, $main);
        $this->assertNotNull($rendition);
        $this->assertSame($source->getId(), $rendition->getFile()?->getId());
    }

    public function testExistingPickSourceRenditionIsKept(): void
    {
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('pick-source-kept');
        $main = $defaults->renditionDefinitions['main'];

        $source = $this->createUrlFile($workspace);
        $picked = $this->createUrlFile($workspace, 'https://cdn.example.com/picked.jpg');
        $asset = $this->createAsset(['workspace' => $workspace]);
        $asset->setSource($source);
        $this->createAssetRendition($asset, $main, $picked);

        self::getService(RenditionBuilder::class)->buildRendition($main, $asset);

        $rendition = self::getService(RenditionManager::class)->getAssetRenditionByDefinition($asset, $main);
        $this->assertSame($picked->getId(), $rendition?->getFile()?->getId());
    }
}
