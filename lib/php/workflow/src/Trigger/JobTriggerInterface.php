<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Trigger;

interface JobTriggerInterface
{
    /**
     * @return bool whether workflow should continue in the process
     */
    public function triggerJob(string $workflowId, string $jobId): bool;
}
