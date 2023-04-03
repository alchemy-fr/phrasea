<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State;

class JobState
{
    public const STATUS_TRIGGERED = 0;
    public const STATUS_SUCCESS = 1;
    public const STATUS_FAILURE = 2;
    public const STATUS_SKIPPED = 3;
    public const STATUS_RUNNING = 4;

    private string $workflowId;
    private string $jobId;

    private int $status;
    private ?array $outputs;

    public function __construct(string $workflowId, string $jobId, int $status, ?array $outputs = null)
    {
        $this->workflowId = $workflowId;
        $this->jobId = $jobId;
        $this->status = $status;
        $this->outputs = $outputs;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function getOutputs(): array
    {
        return $this->outputs;
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }

    public function setOutputs(array $outputs): void
    {
        $this->outputs = $outputs;
    }

    public function __serialize(): array
    {
        return [
            'workflowId' => $this->workflowId,
            'jobId' => $this->jobId,
            'status' => $this->status,
            'outputs' => $this->outputs,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->workflowId = $data['workflowId'];
        $this->jobId = $data['jobId'];
        $this->status = $data['status'];
        $this->outputs = $data['outputs'];
    }
}
