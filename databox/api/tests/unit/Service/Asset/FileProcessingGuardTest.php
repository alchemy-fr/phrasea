<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Asset;

use App\Entity\Core\Asset;
use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Service\Asset\Exception\FileNotProcessableException;
use App\Service\Asset\FileProcessingGuard;
use PHPUnit\Framework\TestCase;

class FileProcessingGuardTest extends TestCase
{
    public function testAcceptedFileIsProcessable(): void
    {
        $file = $this->createFile();

        $this->createGuard()->assertProcessable($file);
        $this->addToAssertionCount(1);
    }

    public function testRejectedContentIsRefused(): void
    {
        $file = $this->createFile();
        $file->rejectContent('Declared type "image/jpeg", content is "text/html"');

        $this->expectException(FileNotProcessableException::class);
        $this->expectExceptionMessage('its content was rejected');
        $this->createGuard()->assertProcessable($file, allowPendingAnalysis: true);
    }

    public function testQuarantinedAssetWithAnAcceptedSourceIsProcessable(): void
    {
        $file = $this->createFile();
        $asset = $this->createAsset($file, AssetStatusEnum::Quarantined);

        $this->assertNull($this->createGuard()->getAssetRefusal($asset));
    }

    public function testFailedAnalysisIsRefused(): void
    {
        $file = $this->createFile();
        $file->setAnalysisResult(File::ANALYSIS_FAILED);

        $this->assertSame('its analysis failed', $this->createGuard()->getRefusal($file, allowPendingAnalysis: true));
    }

    public function testPendingAnalysisIsRefusedWhenTheWorkspaceRequiresIt(): void
    {
        $file = $this->createFile(analysisRequired: true);
        $guard = $this->createGuard();

        $this->assertSame('its analysis is pending', $guard->getRefusal($file));
        $this->assertNull($guard->getRefusal($file, allowPendingAnalysis: true));

        $file->setAnalysisResult(File::ANALYSIS_SUCCESS);
        $this->assertNull($guard->getRefusal($file));
    }

    public function testPendingAnalysisIsAcceptedWhenTheWorkspaceDoesNotRequireIt(): void
    {
        $file = $this->createFile();

        $this->assertNull($this->createGuard()->getRefusal($file));
    }

    public function testBypassMakesTheFileProcessable(): void
    {
        $file = $this->createFile(analysisRequired: true);
        $file->rejectContent('Declared type "image/jpeg", content is "text/html"');

        // As BypassQuarantineProcessor does
        $file->bypassAnalysis();

        $this->assertNull($this->createGuard()->getRefusal($file));
    }

    public function testAssetWithoutSource(): void
    {
        $asset = new Asset();
        $asset->setStatus(AssetStatusEnum::Quarantined);

        $this->assertNull($this->createGuard()->getAssetRefusal($asset));
    }

    public function testAssetRefusalChecksItsSource(): void
    {
        $file = $this->createFile();
        $file->rejectContent('Forbidden');

        $this->assertSame('its content was rejected', $this->createGuard()->getAssetRefusal($this->createAsset($file)));
    }

    private function createGuard(): FileProcessingGuard
    {
        return new FileProcessingGuard();
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
