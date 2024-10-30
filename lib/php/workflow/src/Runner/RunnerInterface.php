<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Runner;

use Alchemy\Workflow\Trigger\JobTrigger;

interface RunnerInterface
{
    public function run(JobTrigger $jobTrigger): void;
}
