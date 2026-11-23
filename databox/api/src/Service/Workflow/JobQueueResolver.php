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
 * Every job of a workflow started or continued by a bulk processing goes to "bulk",
 * whatever its own queue, so that a bulk ingestion never delays the interactive ones.
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
            return WorkerQueue::BULK;
        }

        return $job->getQueue();
    }
}
