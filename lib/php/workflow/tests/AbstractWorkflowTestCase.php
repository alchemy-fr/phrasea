<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests;

use Alchemy\Workflow\Executor\Action\ArrayActionRegistry;
use Alchemy\Workflow\Executor\Adapter\BashExecutor;
use Alchemy\Workflow\Executor\Adapter\PhpExecutor;
use Alchemy\Workflow\Executor\EnvContainer;
use Alchemy\Workflow\Executor\Expression\ExpressionParser;
use Alchemy\Workflow\Executor\JobExecutor;
use Alchemy\Workflow\Executor\JobStateManager;
use Alchemy\Workflow\Executor\PlanExecutor;
use Alchemy\Workflow\Loader\YamlLoader;
use Alchemy\Workflow\Model\WorkflowList;
use Alchemy\Workflow\Planner\WorkflowPlanner;
use Alchemy\Workflow\Repository\MemoryWorkflowRepository;
use Alchemy\Workflow\Runner\RuntimeRunner;
use Alchemy\Workflow\State\Repository\MemoryStateRepository;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Alchemy\Workflow\Tests\State\TestStateStateRepository;
use Alchemy\Workflow\Trigger\RuntimeJobTrigger;
use Alchemy\Workflow\Validator\EventValidator;
use Alchemy\Workflow\WorkflowOrchestrator;
use ColinODell\PsrTestLogger\TestLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

abstract class AbstractWorkflowTest extends TestCase
{
    /**
     * @return array{WorkflowOrchestrator, TestStateStateRepository, TestLogger}
     */
    protected function createOrchestrator(
        array $workflowFiles,
        ?StateRepositoryInterface $stateRepository,
        ?OutputInterface $output = null,
        ?EnvContainer $envs = null,
    ): array {
        $actionRegistry = new ArrayActionRegistry();

        $stateRepository = new TestStateStateRepository($stateRepository ?? new MemoryStateRepository());
        $jobStateManager = new JobStateManager($stateRepository);

        $logger = new TestLogger();
        $jobExecutor = new JobExecutor(
            [
                new BashExecutor(),
                new PhpExecutor(),
            ],
            $actionRegistry,
            new ExpressionParser(),
            $jobStateManager,
            $output,
            $logger,
            $envs,
        );
        $loader = new YamlLoader();

        $workflowRepository = new MemoryWorkflowRepository(new WorkflowList(
            array_map(fn (string $src) => $loader->load(__DIR__.'/fixtures/'.$src), $workflowFiles)
        ));

        $planExecutor = new PlanExecutor(
            $workflowRepository,
            $jobExecutor,
            $stateRepository,
        );
        $runner = new RuntimeRunner($planExecutor);
        $jobTrigger = new RuntimeJobTrigger($runner);

        return [new WorkflowOrchestrator(
            $workflowRepository,
            $stateRepository,
            $jobTrigger,
            new EventValidator(),
            new EventDispatcher(),
        ), $stateRepository, $logger];
    }

    protected function createPlanner(array $workflowFiles): WorkflowPlanner
    {
        $loader = new YamlLoader();

        return new WorkflowPlanner(array_map(fn (string $src) => $loader->load(__DIR__.'/fixtures/'.$src), $workflowFiles));
    }

    protected function assertJobResultsStates(array $expected, StateRepositoryInterface $repository, string $workflowId): void
    {
        foreach ($expected as $jobId => $result) {
            if (null === $result) {
                $this->assertNull($repository->getLastJobState($workflowId, $jobId));
            } else {
                $this->assertEquals($result, $repository->getLastJobState($workflowId, $jobId)->getStatus(), $jobId);
            }
        }
    }
}
