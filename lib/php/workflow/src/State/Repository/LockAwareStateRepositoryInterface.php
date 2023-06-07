<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State\Repository;

interface LockAwareStateRepositoryInterface extends StateRepositoryInterface
{
    public function acquireJobLock(string $workflowId, string $jobId): void;

    public function releaseJobLock(string $workflowId, string $jobId): void;
}
