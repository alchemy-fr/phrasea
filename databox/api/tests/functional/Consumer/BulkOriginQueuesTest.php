<?php

declare(strict_types=1);

namespace App\Tests\Functional\Consumer;

use Alchemy\Workflow\Message\JobConsumer;
use App\Consumer\Handler\IngestWorkspaceAsset;
use App\Consumer\WorkerQueue;
use App\Entity\Admin\OperationTask;
use App\Entity\Core\File;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\InterceptedTransports;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * The real wiring of the bulk origin (BulkOriginMiddleware, BulkOrigin and the JobQueueResolver of
 * the application, used by the workflow orchestrator): the jobs of a workflow started by a message
 * of a bulk transport stay on the bulk transports.
 */
class BulkOriginQueuesTest extends AbstractDataboxTestCase
{
    public function testTheJobsOfAWorkspaceIngestionStayOnTheBulkQueues(): void
    {
        self::bootKernel();
        $em = self::getEntityManager();
        $workspace = $this->createWorkspace();
        $asset = $this->createAsset(['workspace' => $workspace, 'no_flush' => true]);
        $file = new File();
        $file->setWorkspace($workspace);
        $file->setStorage(File::STORAGE_S3_MAIN);
        $file->setType('image/jpeg');
        $file->setPath('test/'.uniqid().'.jpg');
        $em->persist($file);
        $asset->setSource($file);

        $task = new OperationTask();
        $task->setOwnerId('custom_owner');
        $task->setTask('ingest-workspace-assets');
        $task->setPayload(['workspaceId' => $workspace->getId()]);
        $em->persist($task);
        $em->flush();

        $interactive = InterceptedTransports::intercept(self::getContainer(), WorkerQueue::P2, WorkerQueue::HEAVY, WorkerQueue::HEAVY_LOW);
        $bulk = InterceptedTransports::intercept(self::getContainer(), WorkerQueue::BULK, WorkerQueue::BULK_HEAVY, WorkerQueue::BULK_HEAVY_LOW);

        // As a worker consuming the bulk transport does
        self::getService(MessageBusInterface::class)->dispatch(new Envelope(
            new IngestWorkspaceAsset($task->getId(), $asset->getId()),
            [new ReceivedStamp(WorkerQueue::BULK)],
        ));

        $isJob = static fn (Envelope $envelope): bool => $envelope->getMessage() instanceof JobConsumer;
        $this->assertNotEmpty(array_filter($bulk->getSent(), $isJob));
        $this->assertSame([], array_values(array_filter($interactive->getSent(), $isJob)));
    }
}
