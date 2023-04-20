<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests\Dumper;

use Alchemy\Workflow\Date\MicroDateTime;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Alchemy\Workflow\State\WorkflowState;
use Alchemy\Workflow\Tests\AbstractWorkflowTest;

abstract class AbstractDumperTest extends AbstractWorkflowTest
{
    protected function createWorkflowState(string $workflowId): WorkflowState
    {
        $intro = new JobState($workflowId, 'intro', JobState::STATUS_SUCCESS);
        $intro->setStartedAt(new MicroDateTime('2000-05-12T12:12:42', 424242));
        $intro->setEndedAt(new MicroDateTime('2000-05-12T12:12:43', 424242));

        $content = new JobState($workflowId, 'content', JobState::STATUS_RUNNING);
        $content->setStartedAt(new MicroDateTime('2000-05-12T12:12:44', 424242));

        $contentBis = new JobState($workflowId, 'content_bis', JobState::STATUS_RUNNING);
        $contentBis->setStartedAt(new MicroDateTime('2000-05-12T12:12:44', 424242));

        $jobMap = [
            [$workflowId, 'intro', $intro],
            [$workflowId, 'never-called', new JobState($workflowId, 'never-called', JobState::STATUS_SKIPPED)],
            [$workflowId, 'content', $content],
            [$workflowId, 'content_bis', $contentBis],
            [$workflowId, 'outro', null],
        ];

        $stateRepository = $this->createMock(StateRepositoryInterface::class);
        $stateRepository
            ->expects($this->exactly(count($jobMap)))
            ->method('getJobState')
            ->will($this->returnValueMap($jobMap));

        return new WorkflowState($stateRepository, 'foo', null, $workflowId);
    }
}
