<?php

declare(strict_types=1);

namespace App\Tests\Unit\File;

use App\Entity\Core\File;
use App\Entity\Core\FileAnalysisStateEnum;
use PHPUnit\Framework\TestCase;

class FileAnalysisStateTest extends TestCase
{
    use FileAnalysisTrait;

    /**
     * @return iterable<string, array{?array, FileAnalysisStateEnum}>
     */
    public static function analysisStateProvider(): iterable
    {
        yield 'never analyzed' => [null, FileAnalysisStateEnum::NotAnalyzed];
        yield 'no analysis needed' => [[], FileAnalysisStateEnum::NotApplicable];
        yield 'success' => [['status' => File::ANALYSIS_SUCCESS], FileAnalysisStateEnum::Passed];
        yield 'failed' => [['status' => File::ANALYSIS_FAILED], FileAnalysisStateEnum::Failed];
        yield 'skipped' => [['status' => File::ANALYSIS_SKIPPED], FileAnalysisStateEnum::Skipped];
        yield 'bypassed' => [['status' => File::ANALYSIS_BYPASSED], FileAnalysisStateEnum::Bypassed];
        yield 'unknown status' => [['status' => 'whatever'], FileAnalysisStateEnum::NotApplicable];
    }

    /**
     * @dataProvider analysisStateProvider
     */
    public function testGetAnalysisState(?array $analysis, FileAnalysisStateEnum $expected): void
    {
        $file = new File();
        $this->assignAnalysis($file, $analysis);

        $this->assertSame($expected, $file->getAnalysisState());
    }

    public function testSetNoAnalysisNeededIsNotApplicableButAccepted(): void
    {
        $file = new File();
        $file->setNoAnalysisNeeded();

        $this->assertSame(FileAnalysisStateEnum::NotApplicable, $file->getAnalysisState());
        $this->assertTrue($file->isAccepted());
    }

    public function testBypassKeepsPreviousResults(): void
    {
        $file = new File();
        $this->assignAnalysis($file, [
            'status' => File::ANALYSIS_FAILED,
            'results' => [['name' => 'checksum', 'output' => []]],
        ]);
        $file->bypassAnalysis();

        $this->assertSame(FileAnalysisStateEnum::Bypassed, $file->getAnalysisState());
        $this->assertTrue($file->isAccepted());
        $this->assertCount(1, $file->getAnalysis()->getResults());
    }
}
