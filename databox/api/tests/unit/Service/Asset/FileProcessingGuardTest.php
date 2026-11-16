<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Asset;

use App\Entity\Core\Asset;
use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Repository\Core\AssetRepository;
use App\Service\Asset\Exception\FileNotProcessableException;
use App\Service\Asset\FileProcessingGuard;
use PHPUnit\Framework\TestCase;

class FileProcessingGuardTest extends TestCase
{
    public function testAcceptedFileIsProcessable(): void
    {
        $file = $this->createFile();

        $this->createGuard()->assertProcessable($file, $this->createAsset($file));
        $this->addToAssertionCount(1);
    }

    public function testRejectedContentIsRefused(): void
    {
        $file = $this->createFile();
        $file->rejectContent('Declared type "image/jpeg", content is "text/html"');

        $this->expectException(FileNotProcessableException::class);
        $this->expectExceptionMessage('its content was rejected');
        $this->createGuard()->assertProcessable($file, $this->createAsset($file), allowPendingAnalysis: true);
    }

    public function testQuarantinedAssetIsRefused(): void
    {
        $file = $this->createFile();
        $asset = $this->createAsset($file, AssetStatusEnum::Quarantined);

        $this->assertStringContainsString('is quarantined', (string) $this->createGuard()->getRefusal($file, $asset, allowPendingAnalysis: true));
    }

    public function testQuarantinedSourceAssetIsFoundWhenNoAssetIsGiven(): void
    {
        $file = $this->createFile();
        $asset = $this->createAsset($file, AssetStatusEnum::Quarantined);

        $repository = $this->createMock(AssetRepository::class);
        $repository->expects($this->once())
            ->method('findBySourceFileIds')
            ->with([$file->getId()])
            ->willReturn([$asset]);

        $this->assertSame(
            sprintf('asset "%s" is quarantined', $asset->getId()),
            new FileProcessingGuard($repository)->getRefusal($file),
        );
    }

    public function testFailedAnalysisIsRefused(): void
    {
        $file = $this->createFile();
        $file->setAnalysisResult(File::ANALYSIS_FAILED);

        $this->assertSame('its analysis failed', $this->createGuard()->getRefusal($file, $this->createAsset($file), allowPendingAnalysis: true));
    }

    public function testPendingAnalysisIsRefusedWhenTheWorkspaceRequiresIt(): void
    {
        $file = $this->createFile(analysisRequired: true);
        $asset = $this->createAsset($file, AssetStatusEnum::Pending);
        $guard = $this->createGuard();

        $this->assertSame('its analysis is pending', $guard->getRefusal($file, $asset));
        $this->assertNull($guard->getRefusal($file, $asset, allowPendingAnalysis: true));

        $file->setAnalysisResult(File::ANALYSIS_SUCCESS);
        $this->assertNull($guard->getRefusal($file, $asset));
    }

    public function testPendingAnalysisIsAcceptedWhenTheWorkspaceDoesNotRequireIt(): void
    {
        $file = $this->createFile();

        $this->assertNull($this->createGuard()->getRefusal($file, $this->createAsset($file)));
    }

    public function testBypassMakesTheFileProcessable(): void
    {
        $file = $this->createFile(analysisRequired: true);
        $file->rejectContent('Declared type "image/jpeg", content is "text/html"');
        $asset = $this->createAsset($file, AssetStatusEnum::Quarantined);

        // As BypassQuarantineProcessor does
        $file->bypassAnalysis();
        $asset->setStatus(AssetStatusEnum::Accepted);

        $this->assertNull($this->createGuard()->getRefusal($file, $asset));
    }

    public function testAssetWithoutSource(): void
    {
        $guard = $this->createGuard();
        $asset = new Asset();
        $asset->setStatus(AssetStatusEnum::Accepted);

        $this->assertNull($guard->getAssetRefusal($asset));

        $asset->setStatus(AssetStatusEnum::Quarantined);
        $this->assertStringContainsString('is quarantined', (string) $guard->getAssetRefusal($asset));
    }

    public function testAssetRefusalChecksItsSource(): void
    {
        $file = $this->createFile();
        $file->rejectContent('Forbidden');

        $this->assertSame('its content was rejected', $this->createGuard()->getAssetRefusal($this->createAsset($file)));
    }

    private function createGuard(): FileProcessingGuard
    {
        $repository = $this->createStub(AssetRepository::class);
        $repository->method('findBySourceFileIds')->willReturn([]);

        return new FileProcessingGuard($repository);
    }

    private function createFile(bool $analysisRequired = false): File
    {
        $workspace = new Workspace();
        $workspace->setFileAnalysisRequired($analysisRequired);

        $file = new File();
        $file->setWorkspace($workspace);

        return $file;
    }

    private function createAsset(File $source, AssetStatusEnum $status = AssetStatusEnum::Accepted): Asset
    {
        $asset = new Asset();
        $asset->setSource($source);
        $asset->setStatus($status);

        return $asset;
    }
}
