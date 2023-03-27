<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\WorkflowState;
use Symfony\Component\Console\Output\OutputInterface;

class JobExecutionContext
{
    private WorkflowState $workflowState;
    private OutputInterface $output;
    private JobState $jobState;

    public function __construct(
        WorkflowState $workflowState,
        JobState $jobState,
        OutputInterface $output
    )
    {
        $this->workflowState = $workflowState;
        $this->output = $output;
        $this->jobState = $jobState;
    }

    public function getWorkflowId(): string
    {
        return $this->workflowState->getId();
    }

    public function getJobId(): string
    {
        return $this->jobState->getJobId();
    }


    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    public function getJobState(): JobState
    {
        return $this->jobState;
    }
}
