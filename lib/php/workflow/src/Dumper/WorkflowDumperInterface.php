<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Dumper;

use Alchemy\Workflow\Planner\Plan;
use Alchemy\Workflow\State\WorkflowState;
use Symfony\Component\Console\Output\OutputInterface;

interface WorkflowDumperInterface
{
    public function dumpWorkflow(WorkflowState $state, Plan $plan, OutputInterface $output): void;
}
