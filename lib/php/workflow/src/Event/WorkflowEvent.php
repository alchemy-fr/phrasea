<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Event;

final class WorkflowEvent
{
    private string $name;

    private array $inputs;

    public function __construct(string $name, array $inputs = [])
    {
        $this->name = $name;
        $this->inputs = $inputs;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getInputs(): array
    {
        return $this->inputs;
    }

    public function __serialize(): array
    {
        return [
            'name' => $this->name,
            'inputs' => $this->inputs,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->name = $data['name'];
        $this->inputs = $data['inputs'];
    }
}
