<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State;

use Alchemy\Workflow\Date\MicroDateTime;
use Ramsey\Uuid\Uuid;

final class JobState
{
    final public const int STATUS_TRIGGERED = 0;
    final public const int STATUS_SUCCESS = 1;
    final public const int STATUS_FAILURE = 2;
    final public const int STATUS_SKIPPED = 3;
    final public const int STATUS_RUNNING = 4;
    final public const int STATUS_ERROR = 5;
    final public const int STATUS_CANCELLED = 6;

    final public const array STATUS_LABELS = [
        self::STATUS_TRIGGERED => 'triggered',
        self::STATUS_SUCCESS => 'success',
        self::STATUS_FAILURE => 'failure',
        self::STATUS_SKIPPED => 'skipped',
        self::STATUS_RUNNING => 'running',
        self::STATUS_ERROR => 'error',
        self::STATUS_CANCELLED => 'cancelled',
    ];

    private readonly string $id;

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

    public function __construct(
        private readonly string $workflowId,
        private readonly string $jobId,
        private int $status = self::STATUS_TRIGGERED,
        ?string $id = null,
        private readonly int $number = 0,
    ) {
        $this->id = $id ?? Uuid::uuid4()->toString();
        $this->triggeredAt = new MicroDateTime();
        $this->outputs = new Outputs();
    }

    public function getId(): string
    {
        return $this->id;
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
            'id' => $this->id,
            'number' => $this->number,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->id = $data['id'] ?? 'unset';
        $this->workflowId = $data['workflowId'];
        $this->jobId = $data['jobId'];
        $this->status = $data['status'];
        $this->outputs = $data['outputs'];
        $this->inputs = $data['inputs'] ?? null;
        $this->triggeredAt = $data['triggeredAt'];
        $this->startedAt = $data['startedAt'] ?? null;
        $this->endedAt = $data['endedAt'] ?? null;
        $this->errors = $data['errors'] ?? [];
        $this->number = $data['number'] ?? 0;
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

    public function getNumber(): int
    {
        return $this->number;
    }
}
