<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

interface ExecutorInterface
{
    public function support(string $name): bool;

    public function execute(string $run, RunContext $context): void;
}
