<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State\Repository;

use Alchemy\Workflow\State\JobState;

interface LockAwareStateRepositoryInterface extends StateRepositoryInterface
{
    public function acquireJobLock(string $workflowId, string $jobStateId): void;

    public function releaseJobLock(string $workflowId, string $jobStateId): void;
}
