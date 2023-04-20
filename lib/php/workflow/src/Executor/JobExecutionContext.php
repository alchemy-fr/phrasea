<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

use Alchemy\Workflow\State\Inputs;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\WorkflowState;
use Symfony\Component\Console\Output\OutputInterface;

readonly class JobExecutionContext
{
    public function __construct(
        private WorkflowState $workflowState,
        private JobState $jobState,
        private OutputInterface $output,
        private EnvContainer $envs,
        private Inputs $inputs,
    )
    {
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    public function getJobState(): JobState
    {
        return $this->jobState;
    }

    public function getInputs(): Inputs
    {
        return $this->inputs;
    }

    public function getWorkflowState(): WorkflowState
    {
        return $this->workflowState;
    }

    public function getEnvs(): EnvContainer
    {
        return $this->envs;
    }
}
