<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Model;

class OnEvent
{
    public function __construct(private readonly string $name, private readonly array $inputs = [])
    {
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
