<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State\Provider;

use Alchemy\Workflow\State\WorkflowState;

class MemoryStateRepository implements StateRepositoryInterface
{
    private array $states = [];

    public function getWorkflow(string $id): WorkflowState
    {
        if (!isset($this->states[$id])) {
            throw new \InvalidArgumentException(sprintf('Workflow state "%s" does not exist', $id));
        }

        return $this->states[$id];
    }

    public function persistWorkflow(WorkflowState $state): void
    {
        $this->states[$state->getId()] = $state;
    }
}
