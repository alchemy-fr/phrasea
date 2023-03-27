<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests\State;

use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\Repository\FileSystemRepository;
use Alchemy\Workflow\State\Repository\MemoryStateRepository;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Alchemy\Workflow\Tests\AbstractWorkflowTest;

class StateRepositoryTest extends AbstractWorkflowTest
{
    /**
     * @dataProvider getCases
     */
    public function testStateAreCorrectlyPersisted(StateRepositoryInterface $stateRepository): void
    {
        $testStateRepositoryDecorator = new TestStateRepository($stateRepository);
        [$orchestrator] = $this->createOrchestrator([
            'echoer.yaml',
        ], $testStateRepositoryDecorator);

        $workflowState = $orchestrator->startWorkflow('Echo something');

        $workflowId = $workflowState->getId();

        $this->assertEquals([
            ['persistWorkflowState', $workflowId],
            ['getJobResultList', $workflowId],
            ['acquireJobLock', $workflowId, 'intro'],
            ['persistJobState', $workflowId, 'intro', JobState::STATUS_TRIGGERED],
            ['releaseJobLock', $workflowId, 'intro'],
            ['getWorkflowState', $workflowId],
            ['acquireJobLock', $workflowId, 'intro'],
            ['getJobState', $workflowId, 'intro'],
            ['persistJobState', $workflowId, 'intro', JobState::STATUS_RUNNING],
            ['persistJobState', $workflowId, 'intro', JobState::STATUS_SUCCESS],
            ['releaseJobLock', $workflowId, 'intro'],

            ['getJobResultList', $workflowId],

            ['acquireJobLock', $workflowId, 'never-called'],
            ['persistJobState', $workflowId, 'never-called', JobState::STATUS_SKIPPED],
            ['releaseJobLock', $workflowId, 'never-called'],

            ['getJobResultList', $workflowId],

            ['acquireJobLock', $workflowId, 'content'],
            ['persistJobState', $workflowId, 'content', JobState::STATUS_TRIGGERED],
            ['releaseJobLock', $workflowId, 'content'],
            ['getWorkflowState', $workflowId],
            ['acquireJobLock', $workflowId, 'content'],
            ['getJobState', $workflowId, 'content'],
            ['persistJobState', $workflowId, 'content', JobState::STATUS_RUNNING],
            ['persistJobState', $workflowId, 'content', JobState::STATUS_SUCCESS],
            ['releaseJobLock', $workflowId, 'content'],

            ['getJobResultList', $workflowId],

            ['acquireJobLock', $workflowId, 'content-bis'],
            ['persistJobState', $workflowId, 'content-bis', JobState::STATUS_TRIGGERED],
            ['releaseJobLock', $workflowId, 'content-bis'],
            ['getWorkflowState', $workflowId],
            ['acquireJobLock', $workflowId, 'content-bis'],
            ['getJobState', $workflowId, 'content-bis'],
            ['persistJobState', $workflowId, 'content-bis', JobState::STATUS_RUNNING],
            ['persistJobState', $workflowId, 'content-bis', JobState::STATUS_SUCCESS],
            ['releaseJobLock', $workflowId, 'content-bis'],

            ['getJobResultList', $workflowId],

            ['acquireJobLock', $workflowId, 'outro'],
            ['persistJobState', $workflowId, 'outro', JobState::STATUS_TRIGGERED],
            ['releaseJobLock', $workflowId, 'outro'],
            ['getWorkflowState', $workflowId],
            ['acquireJobLock', $workflowId, 'outro'],
            ['getJobState', $workflowId, 'outro'],
            ['persistJobState', $workflowId, 'outro', JobState::STATUS_RUNNING],
            ['persistJobState', $workflowId, 'outro', JobState::STATUS_SUCCESS],
            ['releaseJobLock', $workflowId, 'outro'],

            ['getJobResultList', $workflowId],

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
