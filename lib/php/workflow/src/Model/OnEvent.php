<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Model;

class OnEvent
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
}
