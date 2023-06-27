<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Repository;

use Alchemy\Workflow\Event\WorkflowEvent;
use Alchemy\Workflow\Model\Workflow;

final readonly class ChainedWorkflowRepository implements WorkflowRepositoryInterface
{
    /**
     * @param WorkflowRepositoryInterface[] $repositories
     */
    public function __construct(private array $repositories)
    {
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
