<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Trigger;

interface JobTriggerInterface
{
    public function triggerJob(JobTrigger $jobTrigger): void;

    /**
     * @return bool whether workflow should continue in the process
     */
    public function shouldContinue(): bool;

    public function isSynchronous(): bool;
}
