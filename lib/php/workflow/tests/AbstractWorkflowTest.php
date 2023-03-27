<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests;

use Alchemy\Workflow\Executor\Executors\BashExecutor;
use Alchemy\Workflow\Executor\Executors\PhpExecutor;
use Alchemy\Workflow\Executor\JobExecutor;
use Alchemy\Workflow\Executor\PlanExecutor;
use Alchemy\Workflow\Loader\YamlLoader;
use Alchemy\Workflow\Model\WorkflowList;
use Alchemy\Workflow\Planner\WorkflowPlanner;
use Alchemy\Workflow\Repository\MemoryWorkflowRepository;
use Alchemy\Workflow\Runner\RuntimeRunner;
use Alchemy\Workflow\State\JobResultList;
use Alchemy\Workflow\State\Repository\MemoryStateRepository;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Alchemy\Workflow\Tests\State\TestStateRepository;
use Alchemy\Workflow\Trigger\RuntimeJobTrigger;
use Alchemy\Workflow\WorkflowOrchestrator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractWorkflowTest extends TestCase
{
    /**
     * @param array $workflowFiles
     *
     * @return array{WorkflowOrchestrator, TestStateRepository}
     */
    protected function createOrchestrator(array $workflowFiles, ?StateRepositoryInterface $stateRepository, ?OutputInterface $output = null): array
    {
        $jobExecutor = new JobExecutor([
            new BashExecutor(),
            new PhpExecutor(),
        ]);
        $loader = new YamlLoader();

        $workflowRepository = new MemoryWorkflowRepository(new WorkflowList(
            array_map(fn (string $src) => $loader->load(__DIR__.'/fixtures/'.$src), $workflowFiles)
        ));

        $stateRepository = new TestStateRepository($stateRepository ?? new MemoryStateRepository());

        $planExecutor = new PlanExecutor(
            $workflowRepository,
            $stateRepository,
            $jobExecutor,
            $output,
        );
        $runner = new RuntimeRunner($planExecutor);
        $jobTrigger = new RuntimeJobTrigger($runner);

        return [new WorkflowOrchestrator(
            $workflowRepository,
            $stateRepository,
            $jobTrigger
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
