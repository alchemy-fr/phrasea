<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests;

use Alchemy\Workflow\Executor\EnvContainer;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\WorkflowState;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class OrchestratorTest extends AbstractWorkflowTest
{
    public function testEndToEndEchoerWorkflow(): void
    {
        $output = new BufferedOutput();
        [$orchestrator, $stateRepository] = $this->createOrchestrator([
            'echoer.yaml',
        ], null, $output);

        $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        $workflowState = $orchestrator->startWorkflow('Echo something');

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
Running job content_bis
Running step 0
+ echo "content_bis"
content_bis
Running job outro
Running step 0
+ echo "outro".str_repeat('!', 2);
outro!!
EOF, $output->fetch());

        $this->assertJobResultsStates([
            'intro' => JobState::STATUS_SUCCESS,
            'never-called' => JobState::STATUS_SKIPPED,
            'content' => JobState::STATUS_SUCCESS,
            'content_bis' => JobState::STATUS_SUCCESS,
            'outro' => JobState::STATUS_SUCCESS,
        ], $stateRepository, $workflowState->getId());
    }

    public function testEndToEndEchoerComplexWorkflow(): void
    {
        $output = new BufferedOutput();
        [$orchestrator, $stateRepository] = $this->createOrchestrator([
            'echoer-complex.yaml',
        ], null, $output, new EnvContainer([
            'WF_TEST' => 'off',
        ]));

        $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        $workflowState = $orchestrator->startWorkflow('Echo something complex');

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
Running job content_bis
Running step first
+ echo "content_bis"
content_bis
Running job outro
Running step 0
+ echo "outro".str_repeat('bar', 2);
outrobarbar
EOF, $output->fetch());

        $this->assertEquals(WorkflowState::STATUS_SUCCESS, $workflowState->getStatus());
        $this->assertJobResultsStates([
            'intro' => JobState::STATUS_SUCCESS,
            'never-called' => JobState::STATUS_SKIPPED,
            'content' => JobState::STATUS_SUCCESS,
            'content_bis' => JobState::STATUS_SUCCESS,
            'outro' => JobState::STATUS_SUCCESS,
        ], $stateRepository, $workflowState->getId());

        $contentBisState = $stateRepository->getLastJobState($workflowState->getId(), 'content_bis');
        $this->assertEquals([
            'foo' => 'bar',
            'duration' => $contentBisState->getSteps()['first']->getDuration(),
        ], $contentBisState->getOutputs()->getArrayCopy());
    }

    public function testEndToEndEchoerComplexWorkflowWithNeverCalledJob(): void
    {
        $output = new BufferedOutput();
        [$orchestrator, $stateRepository] = $this->createOrchestrator([
            'echoer-complex.yaml',
        ], null, $output);

        $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        $workflowState = $orchestrator->startWorkflow('Echo something complex');

        $this->assertEquals(WorkflowState::STATUS_FAILURE, $workflowState->getStatus());
        $this->assertJobResultsStates([
            'intro' => JobState::STATUS_SUCCESS,
            'never-called' => JobState::STATUS_FAILURE,
            'content' => null,
            'content_bis' => null,
            'outro' => null,
        ], $stateRepository, $workflowState->getId());
    }
}
