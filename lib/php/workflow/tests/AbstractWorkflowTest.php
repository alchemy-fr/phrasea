<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests;

use Alchemy\Workflow\Executor\Executors\BashExecutor;
use Alchemy\Workflow\Executor\Executors\PhpExecutor;
use Alchemy\Workflow\Executor\JobExecutor;
use Alchemy\Workflow\Executor\PlanExecutor;
use Alchemy\Workflow\Loader\YamlLoader;
use Alchemy\Workflow\Planner\WorkflowPlanner;
use Alchemy\Workflow\Runner\RuntimeRunner;
use Alchemy\Workflow\State\JobResultList;
use Alchemy\Workflow\State\Repository\MemoryStateRepository;
use Alchemy\Workflow\Tests\State\TestStateRepository;
use Alchemy\Workflow\WorkflowOrchestrator;
use PHPUnit\Framework\TestCase;

abstract class AbstractWorkflowTest extends TestCase
{
    /**
     * @param array $workflowFiles
     *
     * @return array{WorkflowOrchestrator, TestStateRepository}
     */
    protected function createOrchestrator(array $workflowFiles): array
    {
        $jobExecutor = new JobExecutor([
            new BashExecutor(),
            new PhpExecutor(),
        ]);
        $loader = new YamlLoader();
        $stateRepository = new TestStateRepository(new MemoryStateRepository());

        $planExecutor = new PlanExecutor($jobExecutor);
        $runner = new RuntimeRunner($planExecutor);

        return [new WorkflowOrchestrator(
            array_map(fn (string $src) => $loader->load(__DIR__.'/fixtures/'.$src), $workflowFiles),
            $stateRepository,
            $runner
        ), $stateRepository];
    }
    protected function createPlanner(array $workflowFiles): WorkflowPlanner
    {
        $loader = new YamlLoader();

        return new WorkflowPlanner(array_map(fn (string $src) => $loader->load(__DIR__.'/fixtures/'.$src), $workflowFiles));
    }

    protected function assertJobResultsStates(array $expected, JobResultList $jobResults): void
    {
        foreach ($expected as $jobId => $result) {
            $this->assertEquals($result, $jobResults->getJobResult($jobId)->getStatus());
        }
    }
}
