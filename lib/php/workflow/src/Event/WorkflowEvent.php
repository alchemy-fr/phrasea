<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Event;

use Alchemy\Workflow\State\Inputs;

final class WorkflowEvent
{
    private string $name;

    private Inputs $inputs;

    public function __construct(string $name, array $inputs = [])
    {
        $this->name = $name;
        $this->inputs = new Inputs($inputs);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getInputs(): Inputs
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
