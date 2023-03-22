<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests;

use Alchemy\Workflow\Executor\Executors\BashExecutor;
use Alchemy\Workflow\Executor\Executors\PhpExecutor;
use Alchemy\Workflow\Executor\JobExecutor;
use Alchemy\Workflow\Executor\PlanExecutor;
use Alchemy\Workflow\Loader\YamlLoader;
use Alchemy\Workflow\Runner\RuntimeRunner;
use Alchemy\Workflow\State\JobResultList;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\Provider\MemoryStateRepository;
use Alchemy\Workflow\WorkflowOrchestrator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

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

        $output = new BufferedOutput();
        $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        $workflowState = $orchestrator->startWorkflow('Echo something', null, $output);

        $this->assertNull($workflowState->getEvent());
        $this->assertEquals(<<<'EOF'
Running job intro
Running step 0
+ echo "intro"
intro
Running job content
Running step 0
+ echo "content"
content
Running job content-bis
Running step 0
+ echo "content-bis"
content-bis
Running job outro
Running step 0
+ echo "outro".str_repeat('!', 2);
outro!!
EOF, $output->fetch());
        $this->assertCount(5, $workflowState->getJobResults());

        $this->assertJobResultsStates([
            'intro' => JobState::STATE_SUCCESS,
            'never-called' => JobState::STATE_SKIPPED,
            'content' => JobState::STATE_SUCCESS,
            'content-bis' => JobState::STATE_SUCCESS,
            'outro' => JobState::STATE_SUCCESS,
        ], $workflowState->getJobResults());
    }

    private function assertJobResultsStates(array $expected, JobResultList $jobResults): void
    {
        foreach ($expected as $jobId => $result) {
            $this->assertEquals($result, $jobResults->getJobResult($jobId)->getState());
        }
    }
}
