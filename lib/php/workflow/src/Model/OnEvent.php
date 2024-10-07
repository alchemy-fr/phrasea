<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Model;

readonly class OnEvent
{
    public function __construct(
        private string $name,
        private array $inputs = [])
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
