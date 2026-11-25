<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Command;

use Alchemy\Workflow\WorkflowOrchestrator;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand('alchemy:workflow:run')]
class RunCommand extends Command
{
    public function __construct(
        private readonly WorkflowOrchestrator $orchestrator,
    ) {
        parent::__construct();
    }

    public function __invoke(
        #[Argument('The workflow name to run')]
        string $name,
    ): int {
        $this->orchestrator->startWorkflow($name);

        return 0;
    }
}
