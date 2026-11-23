<?php

declare(strict_types=1);

namespace App\Service\Workflow;

use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\State\WorkflowState;
use Alchemy\Workflow\Trigger\JobQueueResolverInterface;
use App\Consumer\BulkOrigin;
use App\Consumer\WorkerQueue;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Every job of a workflow started or continued by a bulk processing goes to "bulk_heavy"
 * when it is heavy, to "bulk_heavy_low" when it is heavy and not urgent, to "bulk"
 * otherwise, so that a bulk ingestion never delays the interactive ones.
 */
#[AsAlias(id: JobQueueResolverInterface::class)]
final readonly class JobQueueResolver implements JobQueueResolverInterface
{
    public function __construct(
        private BulkOrigin $bulkOrigin,
    ) {
    }

    public function resolveQueue(WorkflowState $state, Job $job): ?string
    {
        if ($this->bulkOrigin->isActive()) {
            return match ($job->getQueue()) {
                WorkerQueue::HEAVY => WorkerQueue::BULK_HEAVY,
                WorkerQueue::HEAVY_LOW => WorkerQueue::BULK_HEAVY_LOW,
                default => WorkerQueue::BULK,
            };
        }

        return $job->getQueue();
    }
}
