<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

class ExecutionOutput
{
    private array $content = [];

    public function __construct()
    {
    }

    public function write(string $line): void
    {
        $this->content[] = $line;
    }

    public function getContent(): array
    {
        return $this->content;
    }
}
