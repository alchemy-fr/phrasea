<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests;

use Alchemy\Workflow\State\JobState;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class OrchestratorTest extends AbstractWorkflowTest
{
    public function testEndToEndWorkflow(): void
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
Running job content-bis
Running step 0
+ echo "content-bis"
content-bis
Running job outro
Running step 0
+ echo "outro".str_repeat('!', 2);
outro!!
EOF, $output->fetch());

        $this->assertJobResultsStates([
            'intro' => JobState::STATUS_SUCCESS,
            'never-called' => JobState::STATUS_SKIPPED,
            'content' => JobState::STATUS_SUCCESS,
            'content-bis' => JobState::STATUS_SUCCESS,
            'outro' => JobState::STATUS_SUCCESS,
        ], $stateRepository, $workflowState->getId());
    }
}
