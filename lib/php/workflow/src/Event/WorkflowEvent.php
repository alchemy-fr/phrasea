<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Event;

class WorkflowEvent
{
    private string $name;

    private array $args;

    public function __construct(string $name, array $args = [])
    {
        $this->name = $name;
        $this->args = $args;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getArgs(): array
    {
        return $this->args;
    }
}
