<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Repository;

use Alchemy\Workflow\Event\WorkflowEvent;
use Alchemy\Workflow\Model\Workflow;

final class ChainedWorkflowRepository implements WorkflowRepositoryInterface
{
    /**
     * @var WorkflowRepositoryInterface[]
     */
    private array $repositories;

    /**
     * @param WorkflowRepositoryInterface[] $repositories
     */
    public function __construct(
        array $repositories,
    )
    {
        $this->repositories = $repositories;
    }

    public function loadWorkflowByName(string $name): ?Workflow
    {
        foreach ($this->repositories as $repository) {
            if (null !== $workflow = $repository->loadWorkflowByName($name)) {
                return $workflow;
            }
        }

        return null;
    }

    public function getWorkflowsByEvent(WorkflowEvent $event): array
    {
        $workflows = [];
        foreach ($this->repositories as $repository) {
            $workflows = array_merge($workflows, $repository->getWorkflowsByEvent($event));
        }

        return $workflows;
    }

    public function loadAll(): void
    {
        foreach ($this->repositories as $repository) {
            $repository->loadAll();
        }
    }
}
