<?php

declare(strict_types=1);

namespace App\Tests\File;

use App\Border\FileAnalyzer\FileDuplicateManager;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\File;
use App\Entity\Core\FileDuplicate;
use App\Entity\Core\Workspace;
use App\File\FileAnalysisReevaluator;
use App\Repository\Core\FileDuplicateRepository;
use App\Repository\Core\FileRepository;
use App\Tests\AbstractDataboxTestCase;

class FileDuplicateTest extends AbstractDataboxTestCase
{
    private const string CHECKSUM = 'a3f5c2e1d4b6a798a3f5c2e1d4b6a798a3f5c2e1d4b6a798a3f5c2e1d4b6a798';

    public function testFindDuplicatesByChecksumOnlyMatchesActiveAssetSources(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();

        $plainFile = $this->createFile($workspace);
        $liveSource = $this->createFile($workspace);
        $this->createAssetWithSource($liveSource);
        $trashedSource = $this->createFile($workspace);
        $trashedAsset = $this->createAssetWithSource($trashedSource);
        $trashedAsset->setDeletedAt(new \DateTimeImmutable());

        $analyzedFile = $this->createFile($workspace);
        self::getEntityManager()->flush();

        $duplicates = self::getService(FileRepository::class)
            ->findDuplicatesByChecksum($analyzedFile, 10);

        $this->assertSame([$liveSource->getId()], array_map(fn (File $f) => $f->getId(), $duplicates));
        $this->assertNotContains($plainFile->getId(), array_map(fn (File $f) => $f->getId(), $duplicates));
    }

    public function testReplaceDuplicatesDiffsLinks(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        $em = self::getEntityManager();

        $file = $this->createFile($workspace);
        $dup1 = $this->createFile($workspace);
        $dup2 = $this->createFile($workspace);
        $em->flush();

        /** @var FileDuplicateManager $manager */
        $manager = self::getService(FileDuplicateManager::class);
        /** @var FileDuplicateRepository $repository */
        $repository = self::getService(FileDuplicateRepository::class);

        $manager->replaceDuplicates($file, ['checksum' => [$dup1->getId(), $dup2->getId()]]);
        $em->flush();
        $this->assertCount(2, $repository->findByFileId($file->getId()));

        // Re-sync with one duplicate less and a different analyzer
        $manager->replaceDuplicates($file, [
            'checksum' => [$dup1->getId()],
            'doc_unique_id' => [$dup2->getId()],
        ]);
        $em->flush();

        $links = $repository->findByFileId($file->getId());
        $this->assertCount(2, $links);
        $byAnalyzer = [];
        foreach ($links as $link) {
            $byAnalyzer[$link->getAnalyzer()] = $link->getDuplicateFile()->getId();
        }
        $this->assertSame($dup1->getId(), $byAnalyzer['checksum']);
        $this->assertSame($dup2->getId(), $byAnalyzer['doc_unique_id']);

        $manager->replaceDuplicates($file, []);
        $em->flush();
        $this->assertCount(0, $repository->findByFileId($file->getId()));
    }

    public function testReevaluateRemovesLastDuplicateAndDequarantinesAsset(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        $em = self::getEntityManager();

        $duplicateSource = $this->createFile($workspace);
        $duplicateAsset = $this->createAssetWithSource($duplicateSource);

        $analyzedFile = $this->createFile($workspace);
        $analyzedFile->setAnalysis($this->buildFailedChecksumAnalysis());
        $quarantined = $this->createAssetWithSource($analyzedFile, AssetStatusEnum::Quarantined);
        $this->createLink($analyzedFile, $duplicateSource);
        $em->flush();

        // Trash the asset owning the duplicate file
        $duplicateAsset->setDeletedAt(new \DateTimeImmutable());
        $em->flush();

        self::getService(FileAnalysisReevaluator::class)
            ->reevaluateFiles([$analyzedFile->getId()]);
        $em->clear();

        $this->assertCount(0, self::getService(FileDuplicateRepository::class)->findByFileId($analyzedFile->getId()));

        $analysis = $em->find(File::class, $analyzedFile->getId())->getAnalysis();
        $this->assertSame(File::ANALYSIS_SUCCESS, $analysis['status']);
        $this->assertArrayNotHasKey('messages', $analysis['results'][0]['output']);

        $this->assertSame(
            AssetStatusEnum::Accepted,
            $em->find(Asset::class, $quarantined->getId())->getStatus(),
        );
    }

    public function testReevaluateKeepsOtherErrorsAndQuarantine(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        $em = self::getEntityManager();

        $analyzedFile = $this->createFile($workspace);
        $analysis = $this->buildFailedChecksumAnalysis();
        $analysis['results'][] = [
            'name' => 'image_dimension',
            'output' => [
                'messages' => [[4, 'dimension_too_small', []]],
            ],
        ];
        $analyzedFile->setAnalysis($analysis);
        $quarantined = $this->createAssetWithSource($analyzedFile, AssetStatusEnum::Quarantined);
        $em->flush();

        // No duplicate link at all: the checksum error must be dropped, the other kept.
        self::getService(FileAnalysisReevaluator::class)
            ->reevaluateFiles([$analyzedFile->getId()]);
        $em->clear();

        $analysis = $em->find(File::class, $analyzedFile->getId())->getAnalysis();
        $this->assertSame(File::ANALYSIS_FAILED, $analysis['status']);
        $this->assertArrayNotHasKey('messages', $analysis['results'][0]['output']);
        $this->assertSame('dimension_too_small', $analysis['results'][1]['output']['messages'][0][1]);

        $this->assertSame(
            AssetStatusEnum::Quarantined,
            $em->find(Asset::class, $quarantined->getId())->getStatus(),
        );
    }

    public function testReevaluateLeavesBypassedAnalysisUntouched(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        $em = self::getEntityManager();

        $analyzedFile = $this->createFile($workspace);
        $analysis = $this->buildFailedChecksumAnalysis();
        $analysis['status'] = File::ANALYSIS_BYPASSED;
        $analyzedFile->setAnalysis($analysis);
        $this->createAssetWithSource($analyzedFile);
        $em->flush();

        self::getService(FileAnalysisReevaluator::class)
            ->reevaluateFiles([$analyzedFile->getId()]);
        $em->clear();

        $analysis = $em->find(File::class, $analyzedFile->getId())->getAnalysis();
        $this->assertSame(File::ANALYSIS_BYPASSED, $analysis['status']);
        $this->assertSame('duplicate_checksum', $analysis['results'][0]['output']['messages'][0][1]);
    }

    public function testRestoreDuplicateLinksRefailsMatchingAnalyses(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        $em = self::getEntityManager();

        $restoredSource = $this->createFile($workspace);
        $this->createAssetWithSource($restoredSource);

        $analyzedFile = $this->createFile($workspace);
        $analysis = $this->buildFailedChecksumAnalysis();
        // Simulate a previously cleaned-up analysis
        $analysis['status'] = File::ANALYSIS_SUCCESS;
        unset($analysis['results'][0]['output']['messages']);
        $analyzedFile->setAnalysis($analysis);
        $owningAsset = $this->createAssetWithSource($analyzedFile);
        $em->flush();

        self::getService(FileAnalysisReevaluator::class)
            ->restoreDuplicateLinks([$restoredSource->getId()]);
        $em->clear();

        $links = self::getService(FileDuplicateRepository::class)->findByFileId($analyzedFile->getId());
        $this->assertCount(1, $links);
        $this->assertSame($restoredSource->getId(), $links[0]->getDuplicateFile()->getId());
        $this->assertSame('checksum', $links[0]->getAnalyzer());

        $analysis = $em->find(File::class, $analyzedFile->getId())->getAnalysis();
        $this->assertSame(File::ANALYSIS_FAILED, $analysis['status']);
        $this->assertSame('duplicate_checksum', $analysis['results'][0]['output']['messages'][0][1]);
        $this->assertSame(1, $analysis['results'][0]['output']['messages'][0][2]['count']);

        $this->assertSame(
            AssetStatusEnum::Quarantined,
            $em->find(Asset::class, $owningAsset->getId())->getStatus(),
        );
    }

    private function createFile(Workspace $workspace, ?string $checksum = self::CHECKSUM): File
    {
        $em = self::getEntityManager();

        $file = new File();
        $file->setWorkspace($workspace);
        $file->setStorage(File::STORAGE_S3_MAIN);
        $file->setPath('test/'.uniqid().'.jpg');
        $file->setChecksum($checksum);
        $em->persist($file);

        return $file;
    }

    private function createAssetWithSource(File $file, AssetStatusEnum $status = AssetStatusEnum::Accepted): Asset
    {
        $asset = $this->createAsset([
            'workspace' => $file->getWorkspace(),
            'no_flush' => true,
        ]);
        $asset->setSource($file);
        $asset->setStatus($status);

        return $asset;
    }

    private function createLink(File $file, File $duplicateFile, string $analyzer = 'checksum'): FileDuplicate
    {
        $link = new FileDuplicate();
        $link->setFile($file);
        $link->setDuplicateFile($duplicateFile);
        $link->setAnalyzer($analyzer);
        self::getEntityManager()->persist($link);

        return $link;
    }

    private function buildFailedChecksumAnalysis(): array
    {
        return [
            'status' => File::ANALYSIS_FAILED,
            'hash' => 'some-hash',
            'results' => [
                [
                    'name' => 'checksum',
                    'output' => [
                        'messages' => [[4, 'duplicate_checksum', ['count' => 1, 'limit' => 10]]],
                        'data' => ['checksum' => self::CHECKSUM, 'algorithm' => 'sha256', 'stripped' => true],
                    ],
                    'actions' => ['quarantine'],
                ],
            ],
        ];
    }
}
