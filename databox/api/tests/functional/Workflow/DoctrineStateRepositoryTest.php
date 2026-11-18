<?php

declare(strict_types=1);

namespace App\Tests\Functional\Workflow;

use Alchemy\Workflow\Event\WorkflowEvent;
use Alchemy\Workflow\Exception\WorkflowStateNotFoundException;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Alchemy\Workflow\State\WorkflowState;
use Alchemy\WorkflowBundle\Doctrine\DoctrineStateRepository;
use App\Tests\Functional\AbstractDataboxTestCase;

class DoctrineStateRepositoryTest extends AbstractDataboxTestCase
{
    public function testLockingAWorkflowDeletedMeanwhileFails(): void
    {
        self::bootKernel();
        /** @var DoctrineStateRepository $repository */
        $repository = self::getContainer()->get(StateRepositoryInterface::class);
        $state = new WorkflowState($repository, 'test', new WorkflowEvent('test'));
        $repository->persistWorkflowState($state);
        // Managed by the entity manager, as when a job of the workflow ran in this worker
        $repository->getWorkflowState($state->getId());

        // Deleted by another process, like the cascade of an asset deletion
        self::getEntityManager()->getConnection()->executeStatement('DELETE FROM workflow_state WHERE id = ?', [$state->getId()]);

        $this->expectException(WorkflowStateNotFoundException::class);
        $repository->transactional(fn () => $repository->acquireWorkflowLock($state->getId()));
    }
}
