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
    private array $inputs;
    private array $outputs = [];

    public function __construct(
        WorkflowState $workflowState,
        JobState $jobState,
        OutputInterface $output,
        array $inputs
    )
    {
        $this->workflowState = $workflowState;
        $this->output = $output;
        $this->jobState = $jobState;
        $this->inputs = $inputs;
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

    public function getOutputs(): array
    {
        return $this->outputs;
    }

    public function setOutputs(array $outputs): void
    {
        $this->outputs = $outputs;
    }

    public function getInputs(): array
    {
        return $this->inputs;
    }
}
