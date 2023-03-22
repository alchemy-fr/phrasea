<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests\State;

use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\Repository\FileSystemRepository;
use Alchemy\Workflow\State\Repository\MemoryStateRepository;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Alchemy\Workflow\Tests\AbstractWorkflowTest;
use Symfony\Component\Console\Output\NullOutput;

class StateRepositoryTest extends AbstractWorkflowTest
{
    /**
     * @dataProvider getCases
     */
    public function testStateAreCorrectlyPersisted(StateRepositoryInterface $stateRepository): void
    {
        [$orchestrator] = $this->createOrchestrator([
            'echoer.yaml',
        ]);
        $testStateRepositoryDecorator = new TestStateRepository($stateRepository);
        $orchestrator->setStateRepository($testStateRepositoryDecorator);

        $output = new NullOutput();
        $workflowState = $orchestrator->startWorkflow('Echo something', null, $output);

        $workflowId = $workflowState->getId();

        $this->assertEquals([
            ['persistWorkflowState', $workflowId],
            ['getJobState', $workflowId, 'intro'],
            ['persistJobState', $workflowId, 'intro', JobState::STATUS_TRIGGERED],
            ['persistJobState', $workflowId, 'intro', JobState::STATUS_RUNNING],
            ['persistJobState', $workflowId, 'intro', JobState::STATUS_SUCCESS],

            ['getJobState', $workflowId, 'never-called'],
            ['persistJobState', $workflowId, 'never-called', JobState::STATUS_SKIPPED],

            ['getJobState', $workflowId, 'content'],
            ['persistJobState', $workflowId, 'content', JobState::STATUS_TRIGGERED],
            ['persistJobState', $workflowId, 'content', JobState::STATUS_RUNNING],
            ['persistJobState', $workflowId, 'content', JobState::STATUS_SUCCESS],

            ['getJobState', $workflowId, 'content-bis'],
            ['persistJobState', $workflowId, 'content-bis', JobState::STATUS_TRIGGERED],
            ['persistJobState', $workflowId, 'content-bis', JobState::STATUS_RUNNING],
            ['persistJobState', $workflowId, 'content-bis', JobState::STATUS_SUCCESS],

            ['getJobState', $workflowId, 'outro'],
            ['persistJobState', $workflowId, 'outro', JobState::STATUS_TRIGGERED],
            ['persistJobState', $workflowId, 'outro', JobState::STATUS_RUNNING],
            ['persistJobState', $workflowId, 'outro', JobState::STATUS_SUCCESS],

            ['persistWorkflowState', $workflowId],
        ], $testStateRepositoryDecorator->getLogs());
    }

    public function getCases(): array
    {
        return [
            [new MemoryStateRepository()],
            [new FileSystemRepository(__DIR__.'/../var/state')],
        ];
    }
}
