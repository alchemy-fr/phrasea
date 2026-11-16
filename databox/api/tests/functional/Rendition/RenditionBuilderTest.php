<?php

declare(strict_types=1);

namespace App\Tests\Functional\Rendition;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\File;
use App\Entity\Core\FileAnalysisStateEnum;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Workspace;
use App\Service\Asset\Exception\FileNotProcessableException;
use App\Service\Asset\RenditionBuilder;
use App\Service\Storage\RenditionManager;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Api\Rendition\RenditionTestTrait;

final class RenditionBuilderTest extends AbstractDataboxTestCase
{
    use RenditionTestTrait;

    private const string PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\rIDATx\xdac\xf8\xcf\xc0\xf0\x1f\x00\x05\x00\x01\xff\x89\x99=\x1d\x00\x00\x00\x00IEND\xaeB`\x82";

    // Leaves the source untouched: the rendition is the source file itself
    private const string VOID_BUILD = <<<'YAML'
image:
    transformations:
        -
            module: void
YAML;

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

    public function testSourceLeftUntouchedByTheBuildKeepsItsPendingAnalysis(): void
    {
        [$workspace] = $this->createWorkspaceWithDefaults('void-build');
        $definition = $this->createVoidDefinition($workspace);
        $asset = $this->createAssetWithStoredSource($workspace);

        self::getService(RenditionBuilder::class)->buildRendition($definition, $asset);

        $rendition = self::getService(RenditionManager::class)->getAssetRenditionByDefinition($asset, $definition);
        $this->assertSame($asset->getSource()->getId(), $rendition?->getFile()?->getId());
        $this->assertSourceIsNotAnalyzed($asset);
    }

    public function testSourceLeftUntouchedByTheDynamicBuildKeepsItsPendingAnalysis(): void
    {
        [$workspace] = $this->createWorkspaceWithDefaults('void-dynamic-build');
        $asset = $this->createAssetWithStoredSource($workspace);
        $rendition = $this->createDynamicRendition($asset);

        self::getService(RenditionBuilder::class)->buildDynamicRendition($rendition);

        $this->assertSame($asset->getSource()->getId(), $rendition->getFile()?->getId());
        $this->assertSourceIsNotAnalyzed($asset);
    }

    public function testRenditionOfAQuarantinedAssetIsNotBuilt(): void
    {
        [$workspace] = $this->createWorkspaceWithDefaults('quarantined-build');
        $definition = $this->createVoidDefinition($workspace);
        $asset = $this->createAssetWithStoredSource($workspace);
        $asset->setStatus(AssetStatusEnum::Quarantined);
        self::getEntityManager()->flush();

        try {
            self::getService(RenditionBuilder::class)->buildRendition($definition, $asset);
            $this->fail('The build must be refused');
        } catch (FileNotProcessableException $e) {
            $this->assertStringContainsString('is quarantined', $e->getMessage());
        }

        $this->assertNull(self::getService(RenditionManager::class)->getAssetRenditionByDefinition($asset, $definition));
    }

    public function testDynamicRenditionOfARejectedSourceIsNotBuilt(): void
    {
        [$workspace] = $this->createWorkspaceWithDefaults('rejected-dynamic-build');
        $asset = $this->createAssetWithStoredSource($workspace);
        $asset->getSource()->rejectContent('Declared type "image/png", content is "text/html"');
        $rendition = $this->createDynamicRendition($asset);

        $this->expectException(FileNotProcessableException::class);
        $this->expectExceptionMessage('its content was rejected');
        self::getService(RenditionBuilder::class)->buildDynamicRendition($rendition);
    }

    public function testDynamicRenditionOfASourcePendingARequiredAnalysisIsNotBuilt(): void
    {
        [$workspace] = $this->createWorkspaceWithDefaults('pending-dynamic-build');
        $workspace->setFileAnalysisRequired(true);
        $asset = $this->createAssetWithStoredSource($workspace);
        $rendition = $this->createDynamicRendition($asset);

        $this->expectException(FileNotProcessableException::class);
        $this->expectExceptionMessage('its analysis is pending');
        self::getService(RenditionBuilder::class)->buildDynamicRendition($rendition);
    }

    private function createVoidDefinition(Workspace $workspace): RenditionDefinition
    {
        return $this->createRenditionDefinition($workspace, $this->createRenditionPolicy($workspace, 'void'), 'void', [
            'buildMode' => RenditionDefinition::BUILD_MODE_CUSTOM,
            'definition' => self::VOID_BUILD,
        ]);
    }

    private function createDynamicRendition(Asset $asset): AssetRendition
    {
        $rendition = new AssetRendition();
        $rendition->setAsset($asset);
        $rendition->setName('void');
        $rendition->setBuildDefinition(self::VOID_BUILD);
        self::getEntityManager()->persist($rendition);
        self::getEntityManager()->flush();

        return $rendition;
    }

    private function createAssetWithStoredSource(Workspace $workspace): Asset
    {
        $em = self::getEntityManager();
        $path = sprintf('test/%s.png', uniqid());
        self::getService(FileStorageManager::class)->store($path, self::PNG);

        $file = new File();
        $file->setWorkspace($workspace);
        $file->setStorage(File::STORAGE_S3_MAIN);
        $file->setPath($path);
        $file->setType('image/png');
        $file->setOriginalName('pixel.png');
        $file->setExtension('png');
        $file->setSize(strlen(self::PNG));
        $em->persist($file);

        $asset = $this->createAsset(['workspace' => $workspace, 'no_flush' => true]);
        $asset->setSource($file);
        $em->flush();

        return $asset;
    }

    private function assertSourceIsNotAnalyzed(Asset $asset): void
    {
        $em = self::getEntityManager();
        $em->clear();
        $source = $em->find(Asset::class, $asset->getId())->getSource();

        $this->assertFalse($source->isAnalyzed(), 'the build did not analyze the source');
        $this->assertSame(FileAnalysisStateEnum::NotAnalyzed, $source->getAnalysisState());
    }
}
