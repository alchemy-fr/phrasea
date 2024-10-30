<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State\Repository;

interface LockAwareStateRepositoryInterface extends StateRepositoryInterface
{
    public function acquireJobLock(string $workflowId, string $jobStateId): void;

    public function releaseJobLock(string $workflowId, string $jobStateId): void;

    public function acquireWorkflowLock(string $workflowId): void;

    public function releaseWorkflowLock(string $workflowId): void;
}
