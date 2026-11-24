<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Workflow;

use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\State\Repository\MemoryStateRepository;
use Alchemy\Workflow\State\WorkflowState;
use App\Consumer\BulkOrigin;
use App\Consumer\WorkerQueue;
use App\Service\Workflow\JobQueueResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JobQueueResolverTest extends TestCase
{
    #[DataProvider('getCases')]
    public function testResolveQueue(?string $jobQueue, bool $bulk, ?string $expected): void
    {
        $bulkOrigin = new BulkOrigin();
        if ($bulk) {
            $bulkOrigin->enter();
        }
        $job = new Job('job');
        $job->setQueue($jobQueue);

        $queue = new JobQueueResolver($bulkOrigin)->resolveQueue(new WorkflowState(new MemoryStateRepository(), 'workflow', null), $job);

        $this->assertSame($expected, $queue);
    }

    public static function getCases(): iterable
    {
        yield 'interactive, default queue' => [null, false, null];
        yield 'interactive, heavy job' => [WorkerQueue::HEAVY, false, WorkerQueue::HEAVY];
        yield 'interactive, low priority heavy job' => [WorkerQueue::HEAVY_LOW, false, WorkerQueue::HEAVY_LOW];
        yield 'bulk, default queue' => [null, true, WorkerQueue::BULK];
        yield 'bulk, p1 job' => [WorkerQueue::P1, true, WorkerQueue::BULK];
        yield 'bulk, heavy job' => [WorkerQueue::HEAVY, true, WorkerQueue::BULK_HEAVY];
        yield 'bulk, low priority heavy job' => [WorkerQueue::HEAVY_LOW, true, WorkerQueue::BULK_HEAVY_LOW];
    }
}
