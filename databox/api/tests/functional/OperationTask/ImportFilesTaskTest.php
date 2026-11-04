<?php

declare(strict_types=1);

namespace App\Tests\Functional\OperationTask;

use Alchemy\MessengerBundle\Transport\TestTransport;
use App\Consumer\Handler\File\ImportFile;
use App\Entity\Admin\OperationTask;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\OperationTask\OperationTaskManager;
use App\OperationTask\Task\ImportFilesTask;
use App\Tests\Functional\AbstractDataboxTestCase;

class ImportFilesTaskTest extends AbstractDataboxTestCase
{
    public function testDispatchesAnImportForEveryUrlFileOfTheWorkspace(): void
    {
        $em = self::getEntityManager();
        $workspace = $this->getOrCreateDefaultWorkspace();

        $otherWorkspace = new Workspace();
        $otherWorkspace->setName('Other');
        $otherWorkspace->setSlug('other');
        $otherWorkspace->setOwnerId('custom_owner');
        $em->persist($otherWorkspace);

        $urlFile1 = $this->createFile($workspace, File::STORAGE_URL);
        $urlFile2 = $this->createFile($workspace, File::STORAGE_URL);
        $this->createFile($workspace, File::STORAGE_S3_MAIN);
        $this->createFile($otherWorkspace, File::STORAGE_URL);

        $task = new OperationTask();
        $task->setOwnerId('custom_owner');
        $task->setTask(ImportFilesTask::getName());
        $task->setPayload(['workspaceId' => $workspace->getId()]);
        $em->persist($task);
        $em->flush();

        /** @var TestTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.p2');
        $inMemory = $transport->intercept();

        self::getService(OperationTaskManager::class)->handleTask($task);

        $fileIds = array_map(
            static fn ($envelope): string => $envelope->getMessage()->getFileId(),
            array_filter($inMemory->getSent(), static fn ($envelope): bool => $envelope->getMessage() instanceof ImportFile),
        );
        sort($fileIds);
        $expected = [$urlFile1->getId(), $urlFile2->getId()];
        sort($expected);
        $this->assertSame($expected, $fileIds);

        $task = $em->find(OperationTask::class, $task->getId());
        $this->assertSame(OperationTask::STATUS_COMPLETED, $task->getStatus());
        $this->assertStringContainsString('Dispatched 2 file(s) for import.', $task->getOutput());
    }

    private function createFile(Workspace $workspace, string $storage): File
    {
        $file = new File();
        $file->setWorkspace($workspace);
        $file->setStorage($storage);
        $file->setPath(File::STORAGE_URL === $storage ? 'https://example.com/'.uniqid().'.jpg' : 'test/'.uniqid().'.jpg');
        self::getEntityManager()->persist($file);

        return $file;
    }
}
