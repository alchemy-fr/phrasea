<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests;

use Alchemy\Workflow\Executor\Executors\BashExecutor;
use Alchemy\Workflow\Executor\Executors\PhpExecutor;
use Alchemy\Workflow\Executor\JobExecutor;
use Alchemy\Workflow\Executor\PlanExecutor;
use Alchemy\Workflow\Loader\YamlLoader;
use Alchemy\Workflow\Runner\RuntimeRunner;
use Alchemy\Workflow\State\Provider\MemoryStateRepository;
use Alchemy\Workflow\WorkflowOrchestrator;
use PHPUnit\Framework\TestCase;

class OrchestratorTest extends TestCase
{
    public function testEndToEndWorkflow(): void
    {
        $jobExecutor = new JobExecutor([
            new BashExecutor(),
            new PhpExecutor(),
        ]);
        $loader = new YamlLoader();
        $stateRepository = new MemoryStateRepository();

        $planExecutor = new PlanExecutor($jobExecutor);
        $runner = new RuntimeRunner($planExecutor);

        $orchestrator = new WorkflowOrchestrator(
            [
                $loader->load(__DIR__.'/fixtures/echoer.yaml'),
            ],
            $stateRepository,
            $runner
        );

        $workflowState = $orchestrator->startWorkflow('Echo something');

        $this->assertNull($workflowState->getEvent());
        $this->assertCount(5, $workflowState->getJobResults());

        dump($workflowState->getJobResults()->getArrayCopy());
    }
}
