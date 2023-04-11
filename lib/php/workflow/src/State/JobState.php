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
    private ?string $error = null;
    private ?array $outputs;
    private ?\DateTimeImmutable $triggeredAt = null;
    private ?\DateTimeImmutable $startedAt = null;
    private ?\DateTimeImmutable $endedAt = null;

    public function __construct(string $workflowId, string $jobId, int $status, ?array $outputs = null)
    {
        $this->workflowId = $workflowId;
        $this->jobId = $jobId;
        $this->status = $status;
        $this->outputs = $outputs;
        $this->triggeredAt = new \DateTimeImmutable();
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function getOutputs(): ?array
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

    public function getTriggeredAt(): \DateTimeImmutable
    {
        return $this->triggeredAt;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): void
    {
        $this->startedAt = $startedAt;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function setEndedAt(?\DateTimeImmutable $endedAt): void
    {
        $this->endedAt = $endedAt;
    }

    public function getDuration(): ?int
    {
        if (null !== $this->endedAt) {
            return $this->endedAt->getTimestamp() - $this->startedAt->getTimestamp();
        }

        return null;
    }

    public function getDurationString(): string
    {
        return StateUtil::getFormattedDuration($this->getDuration());
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): void
    {
        $this->error = $error;
    }

    public function __serialize(): array
    {
        return [
            'workflowId' => $this->workflowId,
            'jobId' => $this->jobId,
            'status' => $this->status,
            'outputs' => $this->outputs,
            'triggeredAt' => $this->triggeredAt,
            'startedAt' => $this->startedAt,
            'endedAt' => $this->endedAt,
            'error' => $this->error,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->workflowId = $data['workflowId'];
        $this->jobId = $data['jobId'];
        $this->status = $data['status'];
        $this->outputs = $data['outputs'];
        $this->triggeredAt = $data['triggeredAt'];
        $this->startedAt = $data['startedAt'];
        $this->endedAt = $data['endedAt'];
        $this->error = $data['error'] ?? null;
    }
}
