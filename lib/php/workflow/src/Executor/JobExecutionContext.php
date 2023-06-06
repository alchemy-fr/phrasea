<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

use Alchemy\Workflow\State\Inputs;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\WorkflowState;
use Symfony\Component\Console\Output\OutputInterface;

class JobExecutionContext
{
    public function __construct(
        private readonly WorkflowState $workflowState,
        private readonly JobState $jobState,
        private readonly OutputInterface $output,
        private readonly EnvContainer $envs,
        private Inputs $inputs,
    ) {
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

    public function replaceInputs(Inputs $inputs): void
    {
        $this->inputs = $inputs;
    }
}
