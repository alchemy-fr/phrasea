<?php

declare(strict_types=1);

namespace App\Tests\Unit\File;

use App\Entity\Core\File;
use App\Entity\Core\FileAnalysisStateEnum;
use PHPUnit\Framework\TestCase;

class FileContentRejectionTest extends TestCase
{
    private const string MESSAGE = 'Declared type "image/jpeg", content is "text/html"';

    public function testRejectedContentFailsTheAnalysis(): void
    {
        $file = new File();
        $file->rejectContent(self::MESSAGE);

        $this->assertTrue($file->isContentRejected());
        $this->assertFalse($file->isAccepted());
        $this->assertSame(FileAnalysisStateEnum::Failed, $file->getAnalysisState());
        $this->assertSame(self::MESSAGE, $file->getAnalysis()->getMessage());
    }

    public function testASuccessfulAnalysisDoesNotAcceptARejectedContent(): void
    {
        $file = new File();
        $file->rejectContent(self::MESSAGE);

        $file->setAnalysisResult(File::ANALYSIS_SUCCESS);
        $this->assertFalse($file->isAccepted());

        $file->setAnalysisStatus(File::ANALYSIS_SKIPPED);
        $this->assertFalse($file->isAccepted());
        $this->assertTrue($file->isContentRejected());
    }

    public function testBypassAcceptsARejectedContent(): void
    {
        $file = new File();
        $file->rejectContent(self::MESSAGE);

        $file->bypassAnalysis();

        $this->assertFalse($file->isContentRejected());
        $this->assertTrue($file->isAccepted());
        $this->assertSame(FileAnalysisStateEnum::Bypassed, $file->getAnalysisState());
        $this->assertSame(self::MESSAGE, $file->getAnalysis()->getMessage(), 'the reason is kept for reference');
    }

    public function testClearingTheRejectionResetsTheAnalysis(): void
    {
        $file = new File();
        $file->rejectContent(self::MESSAGE);

        $file->clearContentRejection();

        $this->assertFalse($file->isContentRejected());
        $this->assertTrue($file->isAccepted());
        $this->assertSame(FileAnalysisStateEnum::NotAnalyzed, $file->getAnalysisState());
    }

    public function testClearingWithoutRejectionKeepsTheAnalysis(): void
    {
        $file = new File();
        $file->setAnalysisResult(File::ANALYSIS_FAILED);

        $file->clearContentRejection();

        $this->assertFalse($file->isAccepted());
        $this->assertSame(FileAnalysisStateEnum::Failed, $file->getAnalysisState());
    }

    public function testResetLiftsTheRejection(): void
    {
        $file = new File();
        $file->rejectContent(self::MESSAGE);

        $file->resetAnalysis();

        $this->assertFalse($file->isContentRejected());
        $this->assertTrue($file->isAccepted());
    }
}
