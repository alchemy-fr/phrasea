<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State;

use Alchemy\Workflow\Date\MicroDateTime;

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
    private array $errors = [];
    private Outputs $outputs;

    /**
     * @var array<string, StepState>
     */
    private array $steps = [];
    private readonly MicroDateTime $triggeredAt;
    private ?MicroDateTime $startedAt = null;
    private ?MicroDateTime $endedAt = null;

    public function __construct(string $workflowId, string $jobId, int $status)
    {
        $this->workflowId = $workflowId;
        $this->jobId = $jobId;
        $this->status = $status;
        $this->triggeredAt = new MicroDateTime();
        $this->outputs = new Outputs();
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function initStep(string $id): StepState
    {
        $stepState = new StepState($id);
        $this->steps[$id] = $stepState;

        return $stepState;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function getOutputs(): Outputs
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

    public function getTriggeredAt(): MicroDateTime
    {
        return $this->triggeredAt;
    }

    public function getStartedAt(): ?MicroDateTime
    {
        return $this->startedAt;
    }

    public function setStartedAt(?MicroDateTime $startedAt): void
    {
        $this->startedAt = $startedAt;
    }

    public function getEndedAt(): ?MicroDateTime
    {
        return $this->endedAt;
    }

    public function setEndedAt(?MicroDateTime $endedAt): void
    {
        $this->endedAt = $endedAt;
    }

    public function getDuration(): ?float
    {
        return $this->endedAt?->getDiff($this->startedAt);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function addException(\Throwable $exception): void
    {
        $this->errors[] = sprintf('%s [%s:%d]
%s',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString(),
        );
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
            'errors' => $this->errors,
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
        $this->errors = $data['errors'] ?? [];
    }

    public function getSteps(): array
    {
        return $this->steps;
    }
}
