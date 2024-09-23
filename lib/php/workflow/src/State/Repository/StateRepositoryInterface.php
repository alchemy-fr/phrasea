<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State\Repository;

use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\WorkflowState;

interface StateRepositoryInterface
{
    /**
     * @throws \InvalidArgumentException if state does not exist
     */
    public function getWorkflowState(string $id): WorkflowState;

    public function persistWorkflowState(WorkflowState $state): void;

    public function getJobState(string $workflowId, string $jobId): ?JobState;

    public function persistJobState(JobState $state): void;

    public function removeJobState(string $workflowId, string $jobId): void;

    public function resetJobState(string $workflowId, string $jobId): void;
}
