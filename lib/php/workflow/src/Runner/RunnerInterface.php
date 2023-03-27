<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Runner;

interface RunnerInterface
{
    public function run(string $workflowId, string $jobId): void;
}
