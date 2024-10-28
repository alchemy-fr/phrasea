<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests\State;

use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\Repository\FileSystemStateRepository;
use Alchemy\Workflow\State\Repository\MemoryStateRepository;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Alchemy\Workflow\State\WorkflowState;
use Alchemy\Workflow\Tests\AbstractWorkflowTest;

class StateRepositoryTest extends AbstractWorkflowTest
{
    /**
     * @dataProvider getCases
     */
    public function testStateAreCorrectlyPersistedForSuccessWorkflow(StateRepositoryInterface $stateRepository): void
    {
        $testStateRepositoryDecorator = new TestStateStateRepository($stateRepository);
        [$orchestrator, , $logger] = $this->createOrchestrator([
            'echoer.yaml',
        ], $testStateRepositoryDecorator);

        $workflowState = $orchestrator->startWorkflow('Echo something');

        $this->assertFalse($logger->hasErrorRecords());
        $this->assertEquals(WorkflowState::STATUS_SUCCESS, $workflowState->getStatus());
        $this->assertNotNull($workflowState->getStartedAt());
        $this->assertNotNull($workflowState->getEndedAt());

        $workflowId = $workflowState->getId();

        $this->assertEquals([
            ['persistWorkflowState', $workflowId, WorkflowState::STATUS_STARTED],
            ['getLastJobState', $workflowId, 'intro'],
            ['acquireJobLock', $workflowId, 'intro'],
            ['createJobState', $workflowId, 'intro'],
            ['persistJobState', $workflowId, 'intro', JobState::STATUS_TRIGGERED],
            ['releaseJobLock', $workflowId, 'intro'],
            ['getWorkflowState', $workflowId],
            ['acquireJobLock', $workflowId, 'intro'],
            ['getLastJobState', $workflowId, 'intro'],
            ['persistJobState', $workflowId, 'intro', JobState::STATUS_RUNNING],
            ['releaseJobLock', $workflowId, 'intro'],
            ['persistJobState', $workflowId, 'intro', JobState::STATUS_SUCCESS],

            ['getLastJobState', $workflowId, 'intro'],
            ['getLastJobState', $workflowId, 'never-called'],

            ['acquireJobLock', $workflowId, 'never-called'],
            ['persistJobState', $workflowId, 'never-called', JobState::STATUS_TRIGGERED],
            ['releaseJobLock', $workflowId, 'never-called'],
            ['getWorkflowState', $workflowId],
            ['acquireJobLock', $workflowId, 'never-called'],
            ['getLastJobState', $workflowId, 'never-called'],
            ['persistJobState', $workflowId, 'never-called', JobState::STATUS_SKIPPED],
            ['releaseJobLock', $workflowId, 'never-called'],

            ['getLastJobState', $workflowId, 'intro'],
            ['getLastJobState', $workflowId, 'never-called'],
            ['getLastJobState', $workflowId, 'content'],

            ['acquireJobLock', $workflowId, 'content'],
            ['persistJobState', $workflowId, 'content', JobState::STATUS_TRIGGERED],
            ['releaseJobLock', $workflowId, 'content'],
            ['getWorkflowState', $workflowId],
            ['acquireJobLock', $workflowId, 'content'],
            ['getLastJobState', $workflowId, 'content'],
            ['persistJobState', $workflowId, 'content', JobState::STATUS_RUNNING],
            ['releaseJobLock', $workflowId, 'content'],
            ['persistJobState', $workflowId, 'content', JobState::STATUS_SUCCESS],

            ['getLastJobState', $workflowId, 'intro'],
            ['getLastJobState', $workflowId, 'never-called'],
            ['getLastJobState', $workflowId, 'content'],
            ['getLastJobState', $workflowId, 'content_bis'],

            ['acquireJobLock', $workflowId, 'content_bis'],
            ['persistJobState', $workflowId, 'content_bis', JobState::STATUS_TRIGGERED],
            ['releaseJobLock', $workflowId, 'content_bis'],
            ['getWorkflowState', $workflowId],
            ['acquireJobLock', $workflowId, 'content_bis'],
            ['getLastJobState', $workflowId, 'content_bis'],
            ['persistJobState', $workflowId, 'content_bis', JobState::STATUS_RUNNING],
            ['releaseJobLock', $workflowId, 'content_bis'],
            ['persistJobState', $workflowId, 'content_bis', JobState::STATUS_SUCCESS],

            ['getLastJobState', $workflowId, 'intro'],
            ['getLastJobState', $workflowId, 'never-called'],
            ['getLastJobState', $workflowId, 'content'],
            ['getLastJobState', $workflowId, 'content_bis'],
            ['getLastJobState', $workflowId, 'outro'],

            ['acquireJobLock', $workflowId, 'outro'],
            ['persistJobState', $workflowId, 'outro', JobState::STATUS_TRIGGERED],
            ['releaseJobLock', $workflowId, 'outro'],
            ['getWorkflowState', $workflowId],
            ['acquireJobLock', $workflowId, 'outro'],
            ['getLastJobState', $workflowId, 'outro'],
            ['persistJobState', $workflowId, 'outro', JobState::STATUS_RUNNING],
            ['releaseJobLock', $workflowId, 'outro'],
            ['persistJobState', $workflowId, 'outro', JobState::STATUS_SUCCESS],

            ['getLastJobState', $workflowId, 'intro'],
            ['getLastJobState', $workflowId, 'never-called'],
            ['getLastJobState', $workflowId, 'content'],
            ['getLastJobState', $workflowId, 'content_bis'],
            ['getLastJobState', $workflowId, 'outro'],

            ['persistWorkflowState', $workflowId, WorkflowState::STATUS_SUCCESS],
        ], $testStateRepositoryDecorator->getLogs());
    }

    /**
     * @dataProvider getCases
     */
    public function testStateAreCorrectlyPersistedForFailJob(StateRepositoryInterface $stateRepository): void
    {
        $testStateRepositoryDecorator = new TestStateStateRepository($stateRepository);
        [$orchestrator] = $this->createOrchestrator([
            'echoer-fail.yaml',
        ], $testStateRepositoryDecorator);

        $workflowState = $orchestrator->startWorkflow('Echo something fail');

        $this->assertEquals(WorkflowState::STATUS_FAILURE, $workflowState->getStatus());
        $this->assertNotNull($workflowState->getStartedAt());
        $this->assertNotNull($workflowState->getEndedAt());

        $workflowId = $workflowState->getId();

        $this->assertEquals([
            ['persistWorkflowState', $workflowId, WorkflowState::STATUS_STARTED],
            ['getLastJobState', $workflowId, 'intro'],
            ['createJobState', $workflowId, 'intro'],
            ['persistJobState', $workflowId, 'intro', JobState::STATUS_TRIGGERED],
            ['getWorkflowState', $workflowId],
            ['acquireJobLock', $workflowId, 'intro-0'],
            ['getJobState', $workflowId, 'intro-0'],
            ['persistJobState', $workflowId, 'intro', JobState::STATUS_RUNNING],
            ['releaseJobLock', $workflowId, 'intro-0'],
            ['persistJobState', $workflowId, 'intro', JobState::STATUS_SUCCESS],

            ['getLastJobState', $workflowId, 'intro'],
            ['getLastJobState', $workflowId, 'never-called'],

            ['createJobState', $workflowId, 'never-called'],
            ['persistJobState', $workflowId, 'never-called', JobState::STATUS_TRIGGERED],
            ['getWorkflowState', $workflowId],
            ['acquireJobLock', $workflowId, 'never-called-0'],
            ['getJobState', $workflowId, 'never-called-0'], // 16
            ['persistJobState', $workflowId, 'never-called', JobState::STATUS_SKIPPED],
            ['releaseJobLock', $workflowId, 'never-called-0'],

            ['getLastJobState', $workflowId, 'intro'],
            ['getLastJobState', $workflowId, 'never-called'],
            ['getLastJobState', $workflowId, 'content'],

            ['createJobState', $workflowId, 'content'],
            ['persistJobState', $workflowId, 'content', JobState::STATUS_TRIGGERED],
            ['getWorkflowState', $workflowId],
            ['acquireJobLock', $workflowId, 'content-0'],
            ['getJobState', $workflowId, 'content-0'],
            ['persistJobState', $workflowId, 'content', JobState::STATUS_RUNNING],
            ['releaseJobLock', $workflowId, 'content-0'],
            ['persistJobState', $workflowId, 'content', JobState::STATUS_FAILURE],

            ['getLastJobState', $workflowId, 'intro'],
            ['getLastJobState', $workflowId, 'never-called'],
            ['getLastJobState', $workflowId, 'content'],

            ['persistWorkflowState', $workflowId, WorkflowState::STATUS_FAILURE],
        ], $testStateRepositoryDecorator->getLogs());
    }

    public function getCases(): array
    {
        return [
            [new MemoryStateRepository()],
            [new FileSystemStateRepository(sys_get_temp_dir().'/workflow-state')],
        ];
    }
}
