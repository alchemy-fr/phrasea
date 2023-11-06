<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State;

use Alchemy\Workflow\Date\MicroDateTime;

class JobState
{
    final public const STATUS_TRIGGERED = 0;
    final public const STATUS_SUCCESS = 1;
    final public const STATUS_FAILURE = 2;
    final public const STATUS_SKIPPED = 3;
    final public const STATUS_RUNNING = 4;
    final public const STATUS_ERROR = 5;

    final public const STATUS_LABELS = [
        self::STATUS_TRIGGERED => 'triggered',
        self::STATUS_SUCCESS => 'success',
        self::STATUS_FAILURE => 'failure',
        self::STATUS_SKIPPED => 'skipped',
        self::STATUS_RUNNING => 'running',
        self::STATUS_ERROR => 'error',
    ];
    private array $errors = [];
    private Outputs $outputs;
    private ?Inputs $inputs = null;

    /**
     * @var array<string, StepState>
     */
    private array $steps = [];
    private readonly MicroDateTime $triggeredAt;
    private ?MicroDateTime $startedAt = null;
    private ?MicroDateTime $endedAt = null;

    public function __construct(private string $workflowId, private string $jobId, private int $status)
    {
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
            'inputs' => $this->inputs,
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
        $this->inputs = $data['inputs'];
        $this->triggeredAt = $data['triggeredAt'];
        $this->startedAt = $data['startedAt'];
        $this->endedAt = $data['endedAt'];
        $this->errors = $data['errors'] ?? [];
    }

    public function getSteps(): array
    {
        return $this->steps;
    }

    public function getInputs(): ?Inputs
    {
        return $this->inputs;
    }

    public function setInputs(?Inputs $inputs): JobState
    {
        $this->inputs = $inputs;

        return $this;
    }
}
