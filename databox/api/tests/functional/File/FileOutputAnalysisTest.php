<?php

declare(strict_types=1);

namespace App\Tests\Functional\File;

use App\Api\Model\Output\FileOutput;
use App\Api\OutputTransformer\FileOutputTransformer;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Entity\Core\FileAnalysisStateEnum;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Unit\File\FileAnalysisTrait;

/**
 * How the analysis state of a file is exposed by the API (the entity-level
 * state machine is covered by App\Tests\Unit\File\FileAnalysisStateTest).
 */
class FileOutputAnalysisTest extends AbstractDataboxTestCase
{
    use FileAnalysisTrait;

    /**
     * `accepted` and `analysisPending` keep their exact previous semantics:
     * `accepted === null` (i.e. `analysisPending === true`) only when the
     * workspace requires the analysis and none has run yet.
     *
     * @return iterable<string, array{bool, ?array, ?bool}>
     */
    public static function acceptedProvider(): iterable
    {
        yield 'enforced, not analyzed' => [true, null, null];
        yield 'enforced, no analysis needed' => [true, [], true];
        yield 'enforced, success' => [true, ['status' => File::ANALYSIS_SUCCESS], true];
        yield 'enforced, skipped' => [true, ['status' => File::ANALYSIS_SKIPPED], true];
        yield 'enforced, bypassed' => [true, ['status' => File::ANALYSIS_BYPASSED], true];
        yield 'enforced, failed' => [true, ['status' => File::ANALYSIS_FAILED], false];
        yield 'not enforced, not analyzed' => [false, null, true];
        yield 'not enforced, failed' => [false, ['status' => File::ANALYSIS_FAILED], true];
    }

    /**
     * @dataProvider acceptedProvider
     */
    public function testAcceptedAndPendingAreUnchanged(bool $enforced, ?array $analysis, ?bool $expectedAccepted): void
    {
        $output = $this->transform($this->createAnalyzedFile($enforced, $analysis), [File::GROUP_LIST]);

        $this->assertSame($expectedAccepted, $output->accepted);
        $this->assertSame(null === $expectedAccepted, $output->isAnalysisPending());
    }

    public function testStateAndEnforcedAreExposedIndependently(): void
    {
        $analysis = ['status' => File::ANALYSIS_FAILED];

        $enforcedOutput = $this->transform($this->createAnalyzedFile(true, $analysis), [File::GROUP_LIST]);
        $this->assertSame(FileAnalysisStateEnum::Failed, $enforcedOutput->analysisState);
        $this->assertTrue($enforcedOutput->analysisEnforced);

        $looseOutput = $this->transform($this->createAnalyzedFile(false, $analysis), [File::GROUP_LIST]);
        // Same failure, but not blocking: the client renders it as informative.
        $this->assertSame(FileAnalysisStateEnum::Failed, $looseOutput->analysisState);
        $this->assertFalse($looseOutput->analysisEnforced);
    }

    public function testReportIsExposedOnTheSingleFileViewEvenWhenAccepted(): void
    {
        $file = $this->createAnalyzedFile(true, ['status' => File::ANALYSIS_SUCCESS, 'results' => []]);

        // GET /files/{id} normalizes with GROUP_LIST (the resource-level context),
        // and the Info tab needs the report of a passing file there.
        $this->assertNotNull($this->transform($file, [File::GROUP_LIST])->analysis);
        $this->assertNotNull($this->transform($file, [File::GROUP_METADATA])->analysis);

        // Embedded in an asset: an accepted file carries no report payload.
        $this->assertNull($this->transform($file, [Asset::GROUP_LIST])->analysis);
    }

    public function testReportIsExposedWhenEmbeddedAndRejected(): void
    {
        $file = $this->createAnalyzedFile(true, ['status' => File::ANALYSIS_FAILED, 'results' => []]);

        $this->assertNotNull($this->transform($file, [Asset::GROUP_LIST])->analysis);
    }

    private function createAnalyzedFile(bool $analysisRequired, ?array $analysis): File
    {
        $em = self::getEntityManager();

        $workspace = $this->getOrCreateDefaultWorkspace();
        $workspace->setFileAnalysisRequired($analysisRequired);
        $em->persist($workspace);

        $file = new File();
        $file->setWorkspace($workspace);
        $file->setStorage(File::STORAGE_S3_MAIN);
        $file->setPath('test/'.uniqid().'.jpg');
        $this->assignAnalysis($file, $analysis);
        $em->persist($file);
        // The transformer reads createdAt/updatedAt, which Gedmo only sets on flush.
        $em->flush();

        return $file;
    }

    private function transform(File $file, array $groups): FileOutput
    {
        $context = ['groups' => $groups];

        /** @var FileOutput $output */
        $output = self::getService(FileOutputTransformer::class)
            ->transform($file, FileOutput::class, $context);

        return $output;
    }
}
